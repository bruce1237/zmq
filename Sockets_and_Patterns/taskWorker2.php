<?php

/**
 * Task worker - design 2
 * parallel task worker with kill signaling 
 */

$context = new ZMQContext();

//  socket to receive messages on 
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->connect('tcp://localhost:5557');

// socket to send message to 
$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->connect("tcp://localhost:5558");

// socket for control input
$controller = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$controller->connect("tcp://localhost:5559");
$controller->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "");



// process messages from receiver and controller
$poll = new ZMQPoll();
$poll->add($receiver, ZMQ::POLL_IN);
$poll->add($controller, ZMQ::POLL_IN);
$readable = $writable = array();

while (true) {
    $events = $poll->poll($readable, $writable);
    
    if ($events > 0) {

        foreach ($readable as $socket) {
            if ($socket === $receiver) {
                $msg = $socket->recv();
                // simple progress indicator for the viewer
                echo $msg, PHP_EOL;

                usleep($msg * 1000);

                // send results to sink
                $sender->send("task [$msg] process COMPLETED");
            } elseif ($socket === $controller) {
                echo "received Controller msg";
                echo $socket->recv();
                echo 'EXIT';
                exit();
            }
        }
    }
}
