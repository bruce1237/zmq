<?php

/**
 * hello world client
 * connects REQ socket to tcp://localhost: 5559
 * sends "hello" to server, sxpects "world" back
 */

$context = new ZMQContext();

//socket to talk to server

$requester = new ZMQSocket($context, ZMQ::SOCKET_REQ);
$requester->connect("tcp://localhost:5559");

echo "start sending ....".PHP_EOL;
for ($request_nbr = 0; $request_nbr < 10; $request_nbr++) {
    echo "sending -- $request_nbr ".PHP_EOL;
    $requester->send("helloo");
    echo "sent, receiving".PHP_EOL;
    $string = $requester->recv();
    echo "Received reply $request_nbr: [$string]".PHP_EOL;
}
