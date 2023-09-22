<?php

/**
 * multithreaded repay, actually using processes due a lack of php threads
 */

function step1()
{
    $context = new ZMQContext();
    // signal downstream to step 2
    $sender = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
    $sender->connect("ipc://step2.ipc");
    $sender->send("");
}

function step2()
{
    $pid = pcntl_fork();
    if ($pid == 0) {
        step1();
        exit();
    }

    $context = new ZMQContext();
    // bind to ipc: endpoint, then start upstream thread
    $receiver = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
    $receiver->bind("ipc://step2.ipc");

    // wait for signal
    $receiver->recv();

    // signal downstream to step 3
    $sender = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
    $sender->connect("ipc://step3.ipc");
    $sender->send("");
}

// start upstream thread then bind to icp: endpoint
$pid = pcntl_fork();
if ($pid == 0) {
    step2();
    exit();
}
$context = new ZMQContext();
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
$receiver->bind("ipc://step3.ipc");

// wait for signal
$receiver->recv();

echo "test Successful!" . PHP_EOL;
