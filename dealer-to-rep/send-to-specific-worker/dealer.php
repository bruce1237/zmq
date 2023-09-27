<?php
$context = new ZMQContext();
$dealer = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
$dealer->bind("tcp://*:1234");

$workers = ["WA", "WB"];

$workQueue = $workers;

for ($tasks = 10; $tasks >= 0; $tasks--) {

    if (!$workQueue) {
        $workQueue = $workers;
    }
    $worker = array_shift($workQueue);
    echo "current worker $worker\n";
    $dealer->send($worker, ZMQ::MODE_SNDMORE);
    $dealer->send("", ZMQ::MODE_SNDMORE);
    $dealer->send("task $tasks $worker");
    echo "send task 1 to workers\n";

    $addr = $dealer->recv();
    $delimiter = $dealer->recv();
    $result = $dealer->recv();;

    echo "get result from worker: $result\n";
    sleep(1);
}
