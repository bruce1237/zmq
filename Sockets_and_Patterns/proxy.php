<?php

/**
 * weather proxy device
 */

$context = new ZMQContext();

// this is where the weather server sits
$frontend = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$frontend->connect("tcp://192.168.55.210:5556");

// this is our public endpoint for subscirbers
$backend = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$backend->bind("tcp://10.1.1.0:8100");

// subscribe on everything;
$frontend->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "");

// or may be can use build-in proxy function ZMQDevice()?
// // start built-in device
// $device = new ZMQDevice($frontend, $backend);
// $device->run();


// shunt message out to our own subscirbers
while (true) {
    while(true){
        // process all parts of the message
        $msg = $frontend->recv();
        $more = $frontend->getSockOpt(ZMQ::SOCKOPT_RCVMORE);
        $backend->send($msg, $more ? ZMQ::MODE_SNDMORE : 0);
        if(!$more){
            break; // last msg part
        }
    }
}
