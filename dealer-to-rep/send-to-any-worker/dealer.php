<?php
$context = new ZMQContext();
$dealer = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
$dealer->bind("tcp://*:1234");




for ($tasks = 10; $tasks >= 0; $tasks--) {

    $dealer->send("", ZMQ::MODE_SNDMORE);
    $dealer->send("task $tasks");
    echo "send task 1 to workers\n";

    $delimiter = $dealer->recv();
    $result = $dealer->recv();;

    echo "get result from worker: $result\n";
    sleep(1);
}
