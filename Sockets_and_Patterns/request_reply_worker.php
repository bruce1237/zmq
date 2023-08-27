<?php

/**
 * hello word server
 * connects REP socket to tcp://*:5560
 * expects "helloo" from client and replies with "world"
 */
$context = new ZMQContext();

// socket to talk to clients
$responder = new ZMQSocket($context, ZMQ::SOCKET_REP);
$responder->connect("tcp://localhost:5560");

while (true) {
    $string = $responder->recv();
    echo "Received Request: [$string]" . PHP_EOL;
    // do some work;
    sleep(1);

    // send reply back to client
    echo "sending...".PHP_EOL;
    $responder->send("Worldd");
    echo "sent...".PHP_EOL;
}
