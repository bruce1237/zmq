<?php
/**
 * pubsub envelop subscriber
 */

//  prepare our context and subscriber
$context = new ZMQContext();
$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$subscriber->connect("tcp://localhost:5563");
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "B");

while(true){
    // read envelop with address
    $key = $subscriber->recv();
    $address = $subscriber->recv();

    // read message contents
    $contents = $subscriber->recv();
    printf("[%s] (%s) %s%s",$key, $address, $contents, PHP_EOL);
}