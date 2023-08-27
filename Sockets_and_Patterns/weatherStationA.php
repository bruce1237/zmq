<?php

$context = new ZMQContext();
$socket = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$socket->bind("tcp://*:5557");
// $socket->bind("tcp://*:6000");

echo "socket server is ready".PHP_EOL;
for ($i = 0; $i<10; $i++) {
    echo $i.PHP_EOL;
    $socket->send("A-MSG-$i");
    
    sleep(1);
}
