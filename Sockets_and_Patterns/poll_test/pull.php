<?php
$context = new ZMQContext();

$subscriberSocket = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$subscriberSocket->connect("tcp://localhost:5557");
$msg = $subscriberSocket->recv();
echo "REC: []".PHP_EOL;