<?php
$context = new ZMQContext();
$publisher = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$publisher->bind("tcp://*:5556");
// $publisher->bind("ipc://weather.ipc");

$count = 0;
while($count < 200){
    $zipcode = mt_rand(10000, 10009);
    $temperature = mt_rand(-80, 135);
    $relhumidity = mt_rand(10, 60);

    //send message to all subscribers
    $update = sprintf("%05d %d %d", $zipcode, $temperature, $relhumidity);
    echo $update, PHP_EOL."C:$count".PHP_EOL;
    $publisher->send($update);
    $count++;
    sleep(1);
    
}