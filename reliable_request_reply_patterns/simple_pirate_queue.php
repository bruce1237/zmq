<?php
/**
 * simple pirate queue
 * this is identical to the LRU pattern, with no reliability mechanisms at all
 * it depends on the client for recovery, runs forever.
 */

 include "../zmsg.php";

 define("MAX_WORKERS", 100);


//  prepare our context and sockets
$context = new ZMQContext();
$frontend = $context->getSocket(ZMQ::SOCKET_ROUTER);
$frontend->bind("tcp://*:5555");  // for clients

$backend = $context->getSocket(ZMQ::SOCKET_ROUTER);
$backend->bind("tcp://*:5556");  // for workers


// queue of available workers
$available_workers = 0;
$worker_queue = array();
$read = $write = array();


while(true){
    $poll = new ZMQPoll();
    $poll->add($backend, ZMQ::POLL_IN);


    // poll frontend only if we have available workers
    if($available_workers){
        $poll->add($frontend, ZMQ::POLL_IN);
    }

    $events = $poll->poll($read, $write);

    foreach($read as $socket){
        $zmsg = new Zmsg($socket);
        $zmsg->recv();

        // handle worker activity on backend
        if($socket === $backend){
            // use worker address for LRU routing
            assert($available_workers < MAX_WORKERS);
            echo $zmsg->unwrap();
            array_push($worker_queue, $zmsg->unwrap());
            $available_workers++;


            // return reply to client if it's not a READY
            if($zmsg->address() != "READY"){
                $zmsg->set_socket($frontend)->send();
            }
        }elseif($socket === $frontend){
            // now get next client request, route to next worker
            // REQ socket in worker needs an envelope delimiter
            // Dequeue and drop the next worker address
            $zmsg->wrap(array_shift($worker_queue), "");
            $zmsg->set_socket($backend)->send();
            $available_workers--;
        }
    }
}