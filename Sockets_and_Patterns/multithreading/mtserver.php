<?php
/**
 * multithreaded Hello world server, uses process ue to php's lack of threads
 */

function worker_routine()
{
    $context = new ZMQContext();
    // socket to talk to dispatcher

    $receiver = new ZMQSocket($context, ZMQ::SOCKET_REP);
    $receiver->connect("ipc://workers.ipc");

    while(true) {
        $string = $receiver->recv();
        printf("Received request: [%s]%s", $string, PHP_EOL);

        // do some work
        sleep(1);

        // send reply back to client
        $receiver->send("World");
    }
}

// launch pool of worker threads
for ($thread_nbr = 0; $thread_nbr <5; $thread_nbr++){
    $pid = pcntl_fork();
    if($pid == 0){
        worker_routine();
        exit();
    }
}

// prepare our conext and sockets
$context = new ZMQContext();

// socket to talk to clients
$clients = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$clients->bind("tcp://*:5555");

// socket to talk to workers
$workers = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
$workers->bind("ipc://workers.ipc");

// connect work threads to client threads via a queue
$device = new ZMQDevice($clients, $workers);
$device->run();