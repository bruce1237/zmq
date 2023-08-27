<?php
$context = new ZMQContext();

echo "collecting updates from weather server ...", PHP_EOL;

$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$subscriber->connect("tcp://localhost:5558");


$filter = "";  // mean subscribe on everything

$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, $filter);
    

$total_temp = 0;
$string =$subscriber->recv();
echo "RRRRRR:".$string.PHP_EOL;
