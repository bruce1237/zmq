<?php

/**
 * least -recently used(LRU) queue device
 * clietns and workers are shown here as PIC as PHP does not have threads
 */

define("NBR_CLIENTS", 1);
define("NBR_WORKERS", 1);

//  basic request-reply client using REQ socket
function client_thread()
{
    $context = new ZMQContext();
    $client = new ZMQSocket($context, ZMQ::SOCKET_REQ);
    $client->connect("ipc://frontend.ipc");


    // send request, get reply
    $client->send("HELLO");
    printf("client SEND to frontend HELLO: %s", PHP_EOL);
    $reply = $client->recv();
    printf("Client receive from frontend: %s%s", $reply, PHP_EOL);
}


// worker using REQ socket to do LRU routing
function worker_thread()
{
    $context = new ZMQContext();
    $worker = $context->getSocket(ZMQ::SOCKET_REQ);
    $worker->connect("ipc://backend.ipc");

    // tell broker we're ready for work
    $worker->send("READY");
    printf("worker send to backend: READY%s", PHP_EOL);

    while (true) {
        // read and save all frames until we get an empty frame
        // in this example there is only 1 but it could be more
        $address = $worker->recv();
        printf("worker received from backend address: %s%s", $address, PHP_EOL);

        // Additional logic to clean up workers
        if ($address == "END") {
            printf("worker received from backend  END: %s%s", $address, PHP_EOL);
            exit();
        }

        $empty = $worker->recv();
        printf("worker received from backend Empty: %s%s", $empty, PHP_EOL);

        assert(empty($empty));

        // get request, send reply
        $request = $worker->recv();
        printf("Worker received from backend request: %s%s", $request, PHP_EOL);

        $worker->send($address, ZMQ::MODE_SNDMORE);
        printf("worker SEND to backend address: %s%s", $address, PHP_EOL);

        $worker->send("", ZMQ::MODE_SNDMORE);
        printf("worker SEND to backend EMPTY: %s%s", $empty, PHP_EOL);

        $worker->send("OK");
        printf("worker SEND to backend OK: %s", PHP_EOL);
    }
}

function main()
{
    for ($client_nbr = 0; $client_nbr < NBR_CLIENTS; $client_nbr++) {
        $pid = pcntl_fork();
        if ($pid == 0) {
            printf("Start client_thread %d%s", $client_nbr, PHP_EOL);
            client_thread();

            return;
        }
    }

    for ($worker_nbr = 0; $worker_nbr < NBR_WORKERS; $worker_nbr++) {
        $pid = pcntl_fork();
        if ($pid == 0) {
            printf("Start worker_thread %d%s", $worker_nbr, PHP_EOL);
            worker_thread();
            return;
        }
    }

    $context = new ZMQContext();
    $frontend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
    printf("bind frontend %s", PHP_EOL);
    $frontend->bind("ipc://frontend.ipc");

    $backend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
    printf("bind backend %s", PHP_EOL);
    $backend->bind("ipc://backend.ipc");

    /**
     * logic of LRU loop
     * - poll backend always, frontend only if 1+ worker ready
     * - if worker replies, queue worker as ready and forward reply to client if necessary
     * - if client requests, pop next worker and send request to it
     */

    //  queue of available workers
    $available_workers = 0;
    $worker_queue = array();
    $writable = $readable = array();

    while ($client_nbr > 0) {
        $poll = new ZMQPoll();

        // poll front-end only if we have available workers
        if ($available_workers > 0) {
            $poll->add($frontend, ZMQ::POLL_IN);
        }

        // always poll for worker activity on backend
        $poll->add($backend, ZMQ::POLL_IN);
        $events = $poll->poll($readable, $writable);

        if ($events > 0) {
            foreach ($readable as $socket) {
                // handle worker activity on backend
                if ($socket === $backend) {
                    // queue worker address for LRU routing
                    $worker_addr = $socket->recv();
                    printf("backend receives from worker: %s%s", $worker_addr, PHP_EOL);

                    assert($available_workers < NBR_WORKERS);

                    $available_workers++;
                    array_push($worker_queue, $worker_addr);

                    // second frame is empty
                    $empty = $socket->recv();
                    printf("backend receives from worker EMPTY: %s%s", $empty, PHP_EOL);

                    assert(empty($empty));

                    // third frame is READY or else a client reply address
                    $client_addr = $socket->recv();
                    printf("backend receives from worker client_addr: %s%s", $client_addr, PHP_EOL);

                    if ($client_addr != "READY") {
                        $empty = $socket->recv();
                        printf("NR) backend receives from worker EMPTY: %s%s", $empty, PHP_EOL);

                        assert(empty($empty));
                        

                        $reply = $socket->recv();
                        printf("NR) backend receives from worker reply: %s%s", $reply, PHP_EOL);

                        $frontend->send($client_addr, ZMQ::MODE_SNDMORE);
                        printf("NR) frontend send to client client_addr: %s%s", $client_addr, PHP_EOL);

                        $frontend->send("", ZMQ::MODE_SNDMORE);
                        printf("NR) frontend send to client EMPTY: %s", PHP_EOL);

                        $frontend->send($reply);
                        printf("NR) frontend send to client reply: %s%s", $reply, PHP_EOL);

                        // exit after all messages replyed
                        $client_nbr--;
                    }
                } elseif ($socket === $frontend) {
                    // now get next client request, route to LRU worker
                    // client request is [address][empty][request]

                    $client_addr = $socket->recv();
                    printf("frontend receive from client client_add: %s%s", $client_addr, PHP_EOL);

                    $empty = $socket->recv();
                    printf("frontend receive from client EMPTY: %s", PHP_EOL);

                    assert(empty($empty));

                    $request = $socket->recv();
                    printf("frontend receive from client Request: %s%s", $request, PHP_EOL);

                    $worker_addr = array_shift($worker_queue);

                    
                    $backend->send($worker_addr, ZMQ::MODE_SNDMORE);
                    printf("backend send to worker worker_add: %s%s", $worker_addr, PHP_EOL);

                    $backend->send("", ZMQ::MODE_SNDMORE);
                    printf("backend send to worker  EMPTY: %s", PHP_EOL);

                    $backend->send($client_addr, ZMQ::MODE_SNDMORE);
                    printf("backend send to worker  client_add: %s%s", $client_addr, PHP_EOL);
                    
                    $backend->send("", ZMQ::MODE_SNDMORE);
                    printf("backend send to worker EMPTY: %s", PHP_EOL);


                    $backend->send($request);
                    printf("backend send to worker  Request: %s%s", $request, PHP_EOL);
                    $available_workers--;
                }
            }
        }
    }

    // clean up our worker process
    foreach ($worker_queue as $worker) {
        $backend->send($worker, ZMQ::MODE_SNDMORE);
        $backend->send("", ZMQ::MODE_SNDMORE);
        $backend->send("END");
        printf("backend send END %s", PHP_EOL);
    }
    sleep(1);
}

main();
