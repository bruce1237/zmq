<?php
/**
 * reading from multiple sockets
 * using a simple recv loop
 */

 $context = new ZMQContext();
 
 //  connect to ventilator
 $receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
 $receiver->connect("tcp://localhost:5557");

//  connect to weather station
 $subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
 $subscriber->connect("tcp://localhost:5556");
 $subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "10001");

//  process messages from both sockets
// we prioritize traffic from the task ventilator
while(true){
    try{
        for ($rc = 0; !$rc;){
            if($rc = $receiver->recv(ZMQ::MODE_NOBLOCK)){
                echo "process task";
            }
        }
    } catch(ZMQSocketException $e){
        echo $e->getMessage();
    }

    try {
        for ($rc = 0; !$rc;){
            if ($rc = $subscriber->recv(ZMQ::MODE_NOBLOCK)){
                echo "process weather update";
            }
        }
    } catch(ZMQSocketException $e){
        echo $e->getMessage();
    }
    usleep(1);
}