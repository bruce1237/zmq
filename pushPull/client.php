<?php
$context = new ZMQContext();
$socket = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$socket->connect("tcp://127.0.0.1:7000");

echo "socket Client is ready".PHP_EOL;
while(true){
echo $socket->recv().PHP_EOL;
}
