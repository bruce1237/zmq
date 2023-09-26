<?php

/**
 * Least-recently used (LRU) queue device
 * demostrates use the zmsg class
 */

include "zmsg.php";

define("NBR_CLIENTS", 10);
define("NBR_WORKERS", 3);

//  basic request-reply client using REQ socket
function client_thread()
{
    $context = new ZMQContext();
    $client = new ZMQSocket($context, ZMQ::SOCKET_REQ);
    $client->connect("ipc://frontend.ipc");

    // send request, get reply
    $client->send("HELLO");
    $reply = $client->recv();
    printf("Client receives: %s%s", $reply, PHP_EOL);
}

// worker using REQ socket to dfo LRU routing
function worker_thread()
{
    $context = new ZMQContext();
    $worker = $context->getSocket(ZMQ::SOCKET_REQ);
    $worker->connect("ipc://backend.ipc");

    // tell broker we're ready for work
    $worker->send("READY");
    print("worker send READY\n");

    while (true) {
        $zmsg = new Zmsg($worker);
        $zmsg->recv();

        // additional logic to clean up workers
        if ($zmsg->address() == "END") {
            printf("worker received END\n");
            exit();
        }

        printf("worker %s\n", $zmsg->body());

        $zmsg->body_set("OK");
        $zmsg->send();
    }
}

function main()
{
    for ($client_nbr = 0; $client_nbr < NBR_CLIENTS; $client_nbr++) {
        $pid = pcntl_fork();
        if ($pid == 0) {
            client_thread();
            return;
        }
    }

    for ($worker_nbr = 0; $worker_nbr < NBR_WORKERS; $worker_nbr++) {
        $pid = pcntl_fork();
        Worker_thread();
        return;
    }

    $context = new ZMQContext();
    $frontend = $context->getSocket(ZMQ::SOCKET_ROUTER);
    $frontend->bind("ipc://frontend.ipc");

    $backend = $context->getSocket(ZMQ::SOCKET_ROUTER);
    $backend->bind("ipc://backend.ipc");


    /**
     * Logic of LRU loop
     * - poll backend always, frontend only if 1+ worker ready
     * - if worker replies, queue worker as ready and forward reply to client if necessary
     * - if client requests, pop next worker and send request to it
     */

    //  queue of available workers
    $available_workers = 0;
    $worker_queue = array();
    $writable = $readable = array();

    while($client_nbr>0){
        $poll = new ZMQPoll();

        // poll front-end only if we have avaliable wrotkers
        if($available_workers > 0){
            $poll->add($frontend, ZMQ::POLL_IN);
        }

        // always poll for worker activity on backend
        $poll->add($backend, ZMQ::POLL_IN);
        $events = $poll->poll($readable, $writable);


        if ($events > 0){
            foreach($readable as $socket){
                // handle worker activity on backend
                if ($socket == $backend){
                    // queue worker address for LRU routing
                    $zmsg = new Zmsg($socket);
                    $zmsg->recv();
                    assert($available_workers < NBR_WORKERS);
                    $available_workers++;
                    array_push($worker_queue, $zmsg->unwrap());

                    if ($zmsg->body() != "READY"){
                        $zmsg->set_socket($frontend)->send();

                        // exit after all messages relayed
                        $client_nbr--;
                    }
                } elseif ($socket == $frontend){
                    $zmsg = new Zmsg($socket);
                    $zmsg->recv();
                    $zmsg->wrap(array_shift($worker_queue), "");
                    $zmsg->set_socket($backend)->send();
                    $available_workers--;
                }
            }
        }
    }

    // clean up our worker processes
    foreach($worker_queue as $worker){
        $zmsg = new Zmsg($backend);
        $zmsg->body_set('END')->wrap($worker, "")->send();
    }

    sleep(1);
}

main();
