<?php

/**
 * synchronized publisher
 */

define("SUBSCRIBERS_EXPECTED", 3);

$context = new ZMQContext();

//  socket to talk to clients
$publisher = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$publisher->bind("tcp://*:5561");

// socket to receive signals
$syncservice = new ZMQSocket($context, ZMQ::SOCKET_REP);
$syncservice->bind("tcp://*:5562");

// get synchronization from subscribers
$subscribers = 0;
while ($subscribers < SUBSCRIBERS_EXPECTED) {
    // wait for synchronization request
    $string = $syncservice->recv();

    // send synchronization reply
    $syncservice->send(" ");
    $subscribers++;
}


echo "start sending..." . PHP_EOL;
// now broadcaset exactly 1M updates followed by END
for ($update_nbr = 0; $update_nbr < 100; $update_nbr++) {
    $publisher->send("Rhubarb-$update_nbr");
}


echo "send KILL signal" . PHP_EOL;
$publisher->send("END");

sleep(1);
