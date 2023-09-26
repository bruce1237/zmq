<?php

/**
 * custom routing router to dealer
 */

function worker_a()
{
    $context = new ZMQContext();
    $worker = $context->getSocket(ZMQ::SOCKET_DEALER);
    $worker->setSockOpt(ZMQ::SOCKOPT_IDENTITY, 'A');
    $worker->connect("ipc://routing.ipc");

    $total = 0;
    while(true){
        // we receive one part, with the workload
        $request = $worker->recv();
        if ($request == "ENDA") {
            printf("A received: %d%s", $total, PHP_EOL);
            break;
        }
        $total ++;
    }
}

function worker_b()
{
    $context = new ZMQContext();
    $worker = $context->getSocket(ZMQ::SOCKET_DEALER);
    $worker->setSockOpt(ZMQ::SOCKOPT_IDENTITY, 'B');
    $worker->connect("ipc://routing.ipc");

    $total = 0;
    while(true){
        // we receive one part, with the workload
        $request = $worker->recv();
        if ($request == "ENDB") {
            printf("B received: %d%s", $total, PHP_EOL);
            break;
        }
        $total ++;
    }
}

$pid = pcntl_fork();
if($pid ==0){
    worker_a();
    exit();
}

$pid = pcntl_fork();
if($pid ==0){
    worker_b();
    exit();
}

$context = new ZMQContext();
$client = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$client->bind("ipc://routing.ipc");

// wait for threads to stabilize
sleep(1);

// send 10 tasks scatted to A twice as often as B
for ($task_nbr = 0; $task_nbr<10; $task_nbr++){
    if(mt_rand(0,2)>0){
        $client->send("A", ZMQ::MODE_SNDMORE);
    }else{
        $client->send('B', ZMQ::MODE_SNDMORE);
    }

    $client->send("this is workload");
}

$client->send("A", ZMQ::MODE_SNDMORE);
$client->send("ENDA");
$client->send("B", ZMQ::MODE_SNDMORE);
$client->send("ENDB");

sleep(1);