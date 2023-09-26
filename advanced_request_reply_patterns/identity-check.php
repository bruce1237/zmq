<?php
/**
 * demostrate identities as used by the request-reply pattern. 
 * run this program by itself. note that the utility functions s_ are provided by zhelper.h 
 * it gets boring for everyone to keep repeating this code
 */

 include 'zhelpers.php';

 $context = new ZMQContext();

 $sink = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
 $sink->bind('inproc://exmaple');

//  first allow zmq to set the identity
$anonymous = new ZMQSocket($context, ZMQ::SOCKET_REQ);
$anonymous->connect("inproc://example");
$anonymous->send("ROUTER USES a generated 5 byte identity");
// s_dump($sink);
var_dump($sink);


// then set the identity ourselves
$identified = new ZMQSocket($context, ZMQ::SOCKET_REQ);
$identified->setSockOpt(ZMQ::SOCKOPT_IDENTITY, "PEER2");
$identified->connect("inproc://example");
$identified->send("ROUTER socket uses REQ's socket identity");
// s_dump($sink);
var_dump($sink);

