<?php
$context = new ZMQContext();
$worker = new ZMQSocket($context, ZMQ::SOCKET_REP);
$worker->connect("tcp://localhost:1234");

while (true) {
    $msg = $worker->recv();
    echo "recevied task from dealer: [$msg]\n";

    $result = $worker->send("WA DONE [$msg]");
}
