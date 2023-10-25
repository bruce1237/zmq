<?php

include "../../zmsg.php";

$context = new ZMQContext();
$frontend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$backend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$frontend->bind("tcp://*:5555");
$backend->bind("tcp://*:5556");



//  Queue of available workers

$worker_queue = array();
$writeable = $readable = array();
echo "CCCCCC";
while (true) {
    $poll = new ZMQPoll();



    $poll->add($frontend, ZMQ::POLL_IN);
    //  Always poll for worker activity on backend
    $poll->add($backend, ZMQ::POLL_IN);
    $events = $poll->poll($readable, $writeable);
echo "BBBB";
    if ($events > 0) {
        echo "AAAA";
        foreach ($readable as $socket) {
            //  Handle worker activity on backend
            if ($socket === $backend) {
                //  Queue worker address for LRU routing
                $worker_addr = $socket->recv();
                echo "W: received: $worker_addr\n";
                array_push($worker_queue, $worker_addr);

                //  Second frame is empty
                $empty = $socket->recv();

                //  Third frame is READY or else a client reply address
                $client_addr = $socket->recv();

                if ($client_addr != "READY") {
                    $empty = $socket->recv();
                    $reply = $socket->recv();
                    $frontend->send($client_addr, ZMQ::MODE_SNDMORE);
                    $frontend->send("", ZMQ::MODE_SNDMORE);
                    $frontend->send($reply);
                }
            } elseif ($socket === $frontend) {
                //  Now get next client request, route to LRU worker
                //  Client request is [address][empty][request]
                $client_addr = $socket->recv();
                echo "C: received: $client_addr\n";
                $empty = $socket->recv();

                $request = $socket->recv();

                $backend->send(array_shift($worker_queue), ZMQ::MODE_SNDMORE);
                $backend->send("", ZMQ::MODE_SNDMORE);
                $backend->send($client_addr, ZMQ::MODE_SNDMORE);
                $backend->send("", ZMQ::MODE_SNDMORE);
                $backend->send($request);
            }
        }
    }
}






        //     $zmsg = new Zmsg($socket);
        //     $zmsg->recv();
        //     $address = $zmsg->address();
        //     $task = $zmsg->body();

        //     if ($socket === $brokerW) {
        //         echo "received: $task from W\n";

        //         $zmsg->set_socket($brokerC)->send();
        //         echo "forward worker reply to Client\n";
        //     }

        //     if ($socket === $brokerC) {
        //         echo "received: $task from C\n";
        //         $zmsg->set_socket($brokerW)->send();
        //         echo "forward task to worker\n";
        //     }
        // }

        // foreach ($writable as $socket) {
        //     $zmsg = new Zmsg($socket);

        //     if ($socket === $brokerW) {
        //         $zmsg->recv();
        //         $address =  $zmsg->address();
        //         $task =  $zmsg->body();
        //         echo "received: $task from W\n";


        //         $zmsg->set_socket($brokerC)->send();
        //     }

        //     if ($socket === $brokerC) {

        //         $zmsg->recv();
        //         $address =  $zmsg->address();
        //         $task =  $zmsg->body();
        //         echo "received: $task from C\n";

        //         $zmsg->set_socket($brokerW)->send();
        //         echo "forward task to worker\n";
        //     }
        // }
    
// }
