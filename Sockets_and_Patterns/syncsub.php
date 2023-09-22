<?php

/**
 * synchronized subscriber
 */

$context = new ZMQContext();

//  first, connect our subscriber socket
$subscriber = $context->getSocket(ZMQ::SOCKET_SUB);
$subscriber->connect("tcp://localhost:5561");
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "");


// second, synchronize with publisher
$syncclient = $context->getSocket(ZMQ::SOCKET_REQ);
$syncclient->connect("tcp://localhost:5562");

echo "send Ready Signal" . PHP_EOL;
// send a synchronization request
$syncclient->send(" ");


// wait for synchronization reply
$string = $syncclient->recv();

echo "get reply for ready" . PHP_EOL;

// third, get our updates and report how many we got
$update_nbr = 0;
while (true) {
    echo "start receiving update..." . PHP_EOL;
    $string = $subscriber->recv();
    echo "UP $string DONE" . PHP_EOL;
    if ($string == "END") {
        break;
    }
    $update_nbr++;
}

printf("Received %d updates %s", $update_nbr, PHP_EOL);
