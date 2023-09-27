<?php
$context = new ZMQContext();
$req = new ZMQSocket($context, ZMQ::SOCKET_REQ);
$req->setSockOpt(ZMQ::SOCKOPT_IDENTITY, 'B');
$req->connect("tcp://localhost:1234");

for ($i = 0; $i<10; $i++){
    $req->send("this is reqB request: $i");
    echo "get msg: ", $req->recv().PHP_EOL;
    sleep(1);
}

// echo "2 ", $req->recv();
// echo "3 ", $req->recv();
// echo "4 ", $req->recv();
