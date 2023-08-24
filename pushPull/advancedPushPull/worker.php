<?php
/**
 * It receives a message, 
 * sleeps for that number of seconds, 
 * and then signals that it’s finished:
 */
$context = new ZMQContext();
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->connect("tcp://localhost:5557");

$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->connect("tcp://localhost:5558");

while(true) {
    $string = $receiver->recv();

    echo "receiving task No. {$string} then start processing...". PHP_EOL;

    usleep($string*100);
    $result = $string +100;
    echo "process complete, result: {$result}".PHP_EOL;

    $sender->send($result);
}
