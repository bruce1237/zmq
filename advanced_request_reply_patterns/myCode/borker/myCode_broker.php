<?php

include "../../zmsg.php";

$context = new ZMQContext();

$brokerC = $context->getSocket(ZMQ::SOCKET_ROUTER);
$brokerC->bind("ipc://brokerC.ipc");

$brokerW = $context->getSocket(ZMQ::SOCKET_ROUTER);
$brokerW->bind("ipc://brokerW.ipc");

$readable = $writable = array();


while (true) {
    $poll = new ZMQPoll();
    $poll->add($brokerW, ZMQ::POLL_IN);
    $poll->add($brokerC, ZMQ::POLL_IN);

    $events = $poll->poll($readable, $writeable, 10);

    if ($events > 0) {
        foreach ($readable as $socket) {
            $zmsg = new Zmsg($socket);

            if ($socket === $brokerW) {
                $zmsg->recv();
                $address =  $zmsg->address();
                $task =  $zmsg->body();
                echo "received: $task from W\n";


                $zmsg->set_socket($brokerC)->send();
            }

            if ($socket === $brokerC) {

                $zmsg->recv();
                $address =  $zmsg->address();
                $task =  $zmsg->body();
                echo "received: $task from C\n";

                $zmsg->set_socket($brokerW)->send();
                echo "forward task to worker\n";
            }
        }

        foreach ($writable as $socket) {
            $zmsg = new Zmsg($socket);

            if ($socket === $brokerW) {
                $zmsg->recv();
                $address =  $zmsg->address();
                $task =  $zmsg->body();
                echo "received: $task from W\n";


                $zmsg->set_socket($brokerC)->send();
            }

            if ($socket === $brokerC) {

                $zmsg->recv();
                $address =  $zmsg->address();
                $task =  $zmsg->body();
                echo "received: $task from C\n";

                $zmsg->set_socket($brokerW)->send();
                echo "forward task to worker\n";
            }
        }
    }
}
