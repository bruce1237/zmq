<?php

/**
 * reading from multiple sockets by zmq_poll
 */

$context = new ZMQContext();

//  connect to ventilator
$stationA = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$stationA->connect("tcp://localhost:5557");

// connect to weather server
$stationB = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$stationB->connect("tcp://localhost:5556");
// $subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "10001");


//initialize poll set
$poll = new ZMQPoll();
$poll->add($stationA, ZMQ::POLL_IN);
$poll->add($stationB, ZMQ::POLL_IN);


$readable = $writable = array();


while (true) {
    $events = $poll->poll($readable, $writable);
    if($events > 0){
        var_dump($readable);
        var_dump($writable);
        var_dump($stationA);
        var_dump($stationB);
        echo '------'.PHP_EOL;
        foreach($readable as $socket){
            if($socket === $stationB){
                $msg = $socket->recv();
                echo "received B msg: $msg".PHP_EOL;
            } elseif ($socket === $stationA) {
                $msg = $socket->recv();
                echo "received A msg: $msg".PHP_EOL;
            }
        }

        foreach($writable as $socket){
            if($socket === $stationB){
                $msg = $socket->recv();
                echo "received B msg: $msg".PHP_EOL;
            } elseif ($socket === $stationA) {
                $msg = $socket->recv();
                echo "received A msg: $msg".PHP_EOL;
            }
        }
    }
}

echo "this line never reached!".PHP_EOL;