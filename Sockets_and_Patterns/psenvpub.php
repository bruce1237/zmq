<?php

/**
 * pubsub envelop publisher
 */

//  prepare our context and publisher
$context = new ZMQContext();
$publisher = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$publisher->bind("tcp://*:5563");

while (true) {
    // write two messages, each with an envelop and content
    $publisher->send("A", ZMQ::MODE_SNDMORE);
    $publisher->send("We don't want to see this");
    $publisher->send("B", ZMQ::MODE_SNDMORE);
    $publisher->send("Address Of PUB SERVER", ZMQ::MODE_SNDMORE);
    $publisher->send("We would like to see this");
    sleep(1);
}
