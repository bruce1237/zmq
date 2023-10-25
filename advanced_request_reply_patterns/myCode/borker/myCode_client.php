<?php
    $context = new ZMQContext();
    $client = new ZMQSocket($context, ZMQ::SOCKET_REQ);
    $client->connect("tcp://localhost:5555");

    //  Send request, get reply
    $client->send("HELLO");
    echo "C: send HELLO\n";

    $reply = $client->recv();
    echo "C: received: {$reply}\n";