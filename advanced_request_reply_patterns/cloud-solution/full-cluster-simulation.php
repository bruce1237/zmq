<?php

/**
 * broker peering simulation (part3)
 * prototype the full flow of status and tasks
 */

include "../zmsg.php";

define("NBR_CLIENTS", 10);
define("NUR_WORKERS", 3);


/**
 * request-reply client using REQ socket
 * to simulate load, clients issue a burst of requests and then
 * sleep for a random period.
 */

function client_thread($self)
{
    $context = new ZMQContext();
    $client = new ZMQSocket($context, ZMQ::SOCKET_REQ);
    $endpoint = sprintf("ipc://%s-localfe.ipc", $self);
    $client->connect($endpoint);

    $monitor = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
    $endpoint = sprintf("ipc://%s-monitor.ipc", $self);
    $monitor->connect($endpoint);

    $readable = $writeable = array();

    while (true) {
        sleep(mt_rand(0, 4));
        $burst = mt_rand(1, 14);

        while ($burst--) {
            // send request with random hex ID
            $task_id = sprintf("%04X", mt_rand(0, 10000));
            $client->send($task_id);

            //wait max ten seconds for a reply, then complain
            $poll = new ZMQPoll();
            $poll->add($client, ZMQ::POLL_IN);
            $events = $poll->poll($readable, $writeable, 10 * 1000000);

            if ($events > 0) {
                foreach ($readable as $socket) {
                    $zmsg = new Zmsg($socket);
                    $zmsg->recv();
                    // worker is supposed to answer us with our task id
                    assert($zmsg->body() == $task_id);
                }
            } else {
                $monitor->send(sprintf("E:CLIETN EXIT - lost task %s", $task_id));
                exit();
            }
        }
    }
}



// worker using REQ socket to do LRU routing
function worker_thread($self)
{
    $context = new ZMQContext();
    $worker = $context->getSocket(ZMQ::SOCKET_REQ);
    $endpoint = sprintf("ipc://%s-localbe.ipc", $self);
    $worker->connect($endpoint);


    // tell broker we're ready for work
    $worker->send("READY");

    while (true) {
        $zmsg = new Zmsg($worker);
        $zmsg->recv();

        sleep(mt_rand(0, 2));
        $zmsg->send();
    }
}

// first argument is this broker's name
// other arguments are our peers' names

if ($_SERVER['argc'] < 2) {
    echo "syntax: peering2 me {you}...", PHP_EOL;
    exit();
}

$self = $_SERVER['argv'][1];

for ($client_nbr = 0; $client_nbr < NBR_CLIENTS; $client_nbr++) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        client_thread();

        return;
    }
}

for ($worker_nbr = 0; $worker_nbr < NBR_WORKERS; $worker_nbr++) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        worker_thread($self);

        return;
    }
}

printf("I: preparing broker at %s... %s", $self, PHP_EOL);

// prepare our context and sockets
$context = new ZMQContext();

// bind cloud frontend to endpoint
$endpoint = sprintf("ipc://%s-cloud.ipc", $self);

$cloudfe = $context->getSocket(ZMQ::SOCKET_ROUTER);
$cloudfe->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $self);
$cloudfe->bind($endpoint);

// connect cloud backend to all peers
$cloudbe = $context->getSocket(ZMQ::SOCKET_ROUTER);
$cloudbe->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $self);


for ($argn = 2; $argn < $_SERVER['argc']; $argn++) {
    $peer = $_SERVER['argv'][$argn];
    printf("I: connecting to cloud backend at '%s'%s", $peer, PHP_EOL);
    $endpoint = sprintf("ipc://%s-cloud.ipc", $peer);
    $cloudbe->connect($endpoint);
}

// bind state backend / publisher to endpoint
$statebe = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$endpoint = sprintf("ipc://%s-state.ipc", $self);
$statebe->bind($endpoint);


// connect statefe to all peers
$statefe = $context->getSocket(ZMQ::SOCKET_SUB);
$statebe->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "");

for ($argn = 2; $argn < $_SERVER['argc']; $argn++) {
    $peer = $_SERVER['argv'][$argn];
    printf("I: connecting to state backend at '%s'%s", $peer, PHP_EOL);
    $statefe->connect($endpoint);
}

// prepare monitor socket
$monitor = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$endpoint = sprintf("ipc://$s-monitor.ipc", $self);
$monitor->bind($endpoint);

$localfe = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$endpoint = sprintf("ipc://%s-localfe.ipc", $self);
$localfe->bind($endpoint);

$localbe = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$endpoint = sprintf("ipc://%s-localbe.ipc", $self);
$localbe->bind($endpoint);



// interesting part
// -----------------------------------------------
// publish-subscribe flow
// - poll statefe and process capacity updates
// - each time capacity changes, broadcast new value
// 
// Request-reply flow
// - poll primary and process local/cloud replies
// - while worker available, route localfe to local or cloud


// queue of available workers
$local_capacity = $cloud_capacity = 0;
$worker_queue = array();
$readable = $writeable = array();

while (true) {
    $poll = new ZMQPoll();
    $poll->add($localbe, ZMQ::POLL_IN);
    $poll->add($cloudbe, ZMQ::POLL_IN);
    $poll->add($statefe, ZMQ::POLL_IN);
    $poll->add($monitor, ZMQ::POLL_IN);
    $events = 0;

    // if we have no workers anyhow, wait indefinitely
    try {
        $events = $poll->poll($readable, $writeable, $local_capacity ? 1000000 : -1);
    } catch (ZMQPollException) {
        break;
    }

    // track if capacity changes during this iteration
    $previous = $local_capacity;

    foreach ($readable as $socket) {
        $zmsg = new Zmsg($socket);

        // handle reply from local worker
        if ($socket === $localbe) {
            // use worker address for LRU routing
            $zmsg->recv();
            $worker_queue[] = $zmsg->unwrap();
            $local_capacity++;
            if ($zmsg->body() == "READY") {
                $zmsg = null; //don't route it
            }
        } elseif ($socket === $cloudbe) {
            // or handle reply from peer broker
            $zmsg->recv()->unwrap();
        } elseif ($socket === $statefe) {
            // handle capacity updates

            $zmsg->recv();
            $cloud_capacity = $zmsg->body();
            $zmsg = null;
        } elseif ($socket === $monitor) {
            // handle monitor mesage
            $zmsg->recv();
            echo $zmsg->body(), PHP_EOL;
            $zmsg = null;
        }

        if ($zmsg) {
            // route reply to cloud if it's addressed to a broker
            for ($argn = 2; $argn < $_SERVER['argc']; $argn++) {
                if ($zmsg->address() == $_SERVER['argv'][$argn]) {
                    $zmsg->set_socket($cloudfe)->send();
                    $zmsg = null;
                }
            }
        }

        // rouge reply to client if we still need to
        if ($zmsg) {
            $zmsg->set_socket($localfe)->send();
        }
    }

    // now route as many clietns requests as we can handle
    // - if we have local capacity we poll both localfe and clouldfe
    // - if we have cloud capacity onlly, we poll just localfe
    // - route any request locally if we can, else to cloud

    while ($local_capacity + $cloud_capacity) {
        $poll = new ZMQPoll();
        $poll->add($localfe, ZMQ::POLL_IN);

        if ($local_capacity) {
            $poll->add($cloudfe, ZMQ::POLL_IN);
        }

        $reroutable = false;
        $events = $poll->poll($readable, $writeable, 0);

        if ($events > 0) {
            foreach ($readable as $socket) {
                $zmsg = new Zmsg($socket);
                $zmsg->recv();

                if ($local_capacity) {
                    $zmsg->wrap(array_shift($worker_queue), "");
                    $zmsg->set_socket($localbe)->send();
                    $local_capacity--;
                } else {
                    // route to random broker peer
                    printf("I: route request %s to cloud ... %s", $zmsg->body(), PHP_EOL);
                    $zmsg->wrap($_SERVER['argv'][mt_rand(2, ($_SERVER['argc'] - 1))]);
                    $zmsg->set_socket($cloudbe)->send();
                }
            }
        } else {
            break; //no work, go back to backend
        }
    }
    if ($local_capacity != $previous) {
        // broadcast new capacity
        $zmsg = new Zmsg($statebe);
        $zmsg->body_set($local_capacity);
        // we stick our own address onto the envelop
        $zmsg->wrap($self)->send();
    }
}
