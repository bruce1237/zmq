<?php
$context = new ZMQContext();

$router = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$router->bind("ipc://routing.ipc");

$req =  $context->getSocket(ZMQ::SOCKET_REQ);
$req->connect("ipc://routing.ipc");


$req->send("hi");

echo "First:" .$address =  $router->recv() . PHP_EOL;
echo "Second:" . $router->recv() . PHP_EOL;
echo "third:" . $router->recv() . PHP_EOL;
// echo "forth:" . $router->recv() . PHP_EOL;

// echo "send OK" . PHP_EOL;
// $router->send("ok");  // lost as router don't know where to send 
// echo 'R1:' . $req->recv() . PHP_EOL;
// echo 'R2:' . $req->recv() . PHP_EOL;
// echo 'R3:' . $req->recv() . PHP_EOL;
// echo 'R4:' . $req->recv() . PHP_EOL;


$router->send($address, ZMQ::MODE_SNDMORE);
$router->send("", ZMQ::MODE_SNDMORE);
$router->send("OK");
// echo 'LOST' . PHP_EOL;

echo $req->recv();

echo $req->recv();
echo $req->recv();