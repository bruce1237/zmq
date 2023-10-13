<?php

/**
 * asynchronous N-client-to-1-server (DEALER to ROUTER)
 * 
 * while this example runs in a single process, that is just to make it easier to start and stop the example.
 * each task has its own context and conceptually acts as a separate process
 */


include "./zmsg.php";

/**
 * this is our client task
 * it connects to the server, and then sends a request once per second
 * it collects responses as they arrive, and it prints them out.
 * we will run several client tasks in parallel, each with a different random ID
 */


function client_task()
{
    $context = new ZMQContext();
    $client = new ZMQSocket($context, ZMQ::SOCKET_DEALER);

    // generate printable identity for the client
    $identity = sprintf("%04X", rand(0, 0x10000));
    $client->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $identity);
    $client->connect("tcp://localhost:5573");

    $read = $write = array();
    $poll = new ZMQPoll();
    $poll->add($client, ZMQ::POLL_IN);

    $request_nbr = 0;
    while (true) {
        // tick once per second, pulling in arriving messages
        for ($centitick = 0; $centitick < 10; $centitick++) {
            $events = $poll->poll($read, $write, 1000);
            $zmsg = new Zmsg($client);
            if ($events) {
                $zmsg->recv();
                printf("Client receives [%s]: %s%s", $identity, $zmsg->body(), PHP_EOL);
            }
        }
        $zmsg = new Zmsg($client);
        $zmsg->body_fmt("request #%d", ++$request_nbr)->send();
        printf("Client send request %s", $request_nbr -1);
    }
}


/**
 * this is our server t ask
 * it uses the multithreaded server model to deal requests out to a pool of workers and route
 * replies back to clients. one worker can handle one request at a time 
 * but one client can talk to multiple workers at once
 */

function server_task()
{
    // launch pool of worker threads, precise number is not critical
    for ($thread_nbr = 0; $thread_nbr<5; $thread_nbr++){
        $pid = pcntl_fork();
        if($pid ==0){
            print("start server_worker\n");
            server_worker();
            exit();
        }
    }

    $context = new ZMQContext();

    // frontend socket talks to clients over TCP
    $frontend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
    $frontend->bind("tcp://*:5573");

    // backend socket talks to workers over ipc
    $backend = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
    $backend->bind("ipc://backend");

    /**
     * connect backend to frontend via a queue device
     * we could do this
     * device = new ZMQDevices($frontend, $backend);
     * but doing it ourselves means we can debug this more easily
     */

    $read = $write = array();

    // switch messages between frontend and backend
    while(true){
        $poll = new ZMQPoll();
        $poll->add($frontend, ZMQ::POLL_IN);
        $poll->add($backend, ZMQ::POLL_IN);

        $poll->poll($read, $write);

        foreach($read as $socket){
            $zmsg = new Zmsg($socket);
            $zmsg->recv();
            if($socket === $frontend){
                echo "Request from client:";
                echo $zmsg->__toString();
                print("frontend send to backend\n");
                $zmsg->set_socket($backend)->send();
            } elseif ($socket === $backend){
                echo "Requst from worker:";
                echo $zmsg->__toString();
                $zmsg->set_socket($frontend)->send();
                print("backend send to frontend\n");
            }
        }
    }
}

function server_worker()
{
    $context = new ZMQContext();
    $worker = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
    $worker->connect("ipc://backend");
    $zmsg = new Zmsg($worker);

    while(true){
        // the DEALER socket gives us the address envelope and message
        $zmsg->recv();
        assert($zmsg->parts() == 2);

        // send 0..4 replies back
        $replies = rand(0,4);
        for ($reply = 0; $reply < $replies; $reply++){
            // sleep for some fraction of a second
            usleep(rand(0, 1000)+1);
            $zmsg->send(false);
        }
    }
}

/**
 * this is main thread simply starts several clients, and a server, 
 * and then waits for the server to finish
 */

function main()
{
    for($num_clients = 0; $num_clients < 3; $num_clients++){
        $pid = pcntl_fork();
        if ($pid == 0) {
            client_task();
            exit();
        }
    }

    $pid = pcntl_fork();
    if($pid== 0){
        server_task();
        exit();
    }
}

main();