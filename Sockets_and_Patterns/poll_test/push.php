<?php
$context = new ZMQContext();

$subscriberSocket = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$subscriberSocket->bind("tcp://*:5556");

$subscriberSocket->send('SSSSSSSS');

echo "SENT".PHP_EOL;