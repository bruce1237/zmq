<?php
$context = new ZMQContext();

$subscriberSocket = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$subscriberSocket->connect("tcp://localhost:5556");
$subscriberSocket->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "topic");

$publisherSocket = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$publisherSocket->bind("tcp://*:5557");

$poll = new ZMQPoll();
$poll->add($subscriberSocket, ZMQ::POLL_IN);
$poll->add($publisherSocket, ZMQ::POLL_OUT);

while (true) {
    $events = $poll->poll($readable, $writable);
    var_dump($events);

    foreach ($readable as $socket) {
        if ($socket === $subscriberSocket) {
            $message = $subscriberSocket->recv();
            echo "Received: $message\n";
        }
    }

    foreach ($writable as $socket) {
        if ($socket === $publisherSocket) {
            $message = "Hello from PHP";
            $publisherSocket->send($message);
            echo "Sent: $message\n";
        }
    }
}

