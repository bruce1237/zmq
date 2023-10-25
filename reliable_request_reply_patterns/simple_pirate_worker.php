<?php
/**
 * simple pirate worker
 * connects REQ socket to tcp://*:5556
 * implements worker part of LRU queueing
 */

 include "../zmsg.php";


 $context = new ZMQContext();
 $worker = new ZMQSocket($context, ZMQ::SOCKET_REQ);


//  set random identity to make tracing easier
$identity = sprintf("%04X-%04X", rand(0, 0x10000), rand(0, 0x10000));
$worker->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $identity);
$worker->connect("tcp://localhost:5556");

// tell queue we're ready for work
printf("I: (%s) worker ready %s", $identity, PHP_EOL);
$worker->send("READY");


$cycles = 0;
while(true){
    $zmsg = new Zmsg($worker);
    $zmsg->recv();
    $cycles ++;

    // simulate various problems, after a few cycle
    if($cycles>3 && rand(0,3) ==0){
        printf("I: (%s) simulating a crash%s", $identity, PHP_EOL);
        break;
    }else if($cycles>3 && rand(0,3)==0){
        printf("I: (%s) simulating CPU overload%s", $identity, PHP_EOL);
        sleep(5);
    }
    
    printf("I: (%s) normal reply -%s%s", $identity, $zmsg->body(), PHP_EOL);
    sleep(1);
    $zmsg->send();

}