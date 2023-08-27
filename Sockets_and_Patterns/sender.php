<?php

/**
 * hello word server
 * connects REP socket to tcp://*:5560
 * expects "helloo" from client and replies with "world"
 */
$context = new ZMQContext();

// socket to talk to clients
$responder = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$responder->connect("tcp://localhost:5559");

while (true) {
    // send reply back to client
    echo "sending...".PHP_EOL;
    $responder->send("100 Worldd");
    sleep(1);
    echo "sent...".PHP_EOL;
}
