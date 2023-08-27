<?php
/**
 * simple message queuing broker
 * same as request-reply broker but using QUEUE device
 */

 $context = new ZMQContext();

//  socket facing clients
 $frontend = $context->getSocket(ZMQ::SOCKET_PULL);
 $frontend->bind("tcp://*:5559");

//  socket facing service
$backend = $context->getSocket(ZMQ::SOCKET_PUB);
$backend->bind("tcp://*:5558");

// echo $msg =  $frontend->recv();
//     $backend->send("100".$msg);  



// start built-in device
$device = new ZMQDevice($frontend, $backend);
$device->run();