<?php
$context = new ZMQContext();
$worker = new ZMQSocket($context, ZMQ::SOCKET_REP);
$worker->setSockOpt(ZMQ::SOCKOPT_IDENTITY, "WB");
$worker->connect("tcp://localhost:1234");

while (true) {
    $msg = $worker->recv();
    echo "recevied task from dealer: [$msg]\n";
        $result = $worker->send("WB DONE [$msg]");
}
