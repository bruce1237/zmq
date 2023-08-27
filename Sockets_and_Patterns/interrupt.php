<?php

/**
 * interrupt in php
 * shows how to handle ctrl+c
 */

declare(ticks=1); //php internal, make singal handling work
if (!function_exists('pcntl_signal')) {
    printf("Error, you need to enable the pcntl extension in your php");
    exit(1);
}

$running = true;
function signalHandler($signo)
{
    global $running;
    $running = false;
    printf("warning: interrupt received, killing server... %s", PHP_EOL);
}

pcntl_signal(SIGINT, 'signalHandler');

$context = new ZMQContext();

//  socket to talk to clients
$responder = new ZMQSocket($context, ZMQ::SOCKET_REP);
$responder->bind("tcp://*:5558");

while($running){
    echo 'A'.PHP_EOL;
    // wait for next request from client
    try{
        $string = $responder->recv(); //the recv call will throw an ZMQSocketExcetipn when interrupted
        // PHP Fatal error:  Uncaught exception 'ZMQSocketException' with message 'Failed to receive message: Interrupted system call' in interrupt.php:35

    } catch(ZMQSocketException $e){
        if($e->getCode() == 4) {// 4 == EINTR, interrupted system call ( Ctrl+C will interrupt the blocking call as well)
            usleep(1); // don't just continue, otherwise the ticks function won't be processed, and the signal will be ignored, try it
            continue; // ignore it, if our signal handler caught the interrupt as well, the $running flag will be set to false, so we'll break out
        } 
        throw $e; // it's another exception, don't hide it to the user
    }

    printf("received request: [%s]%s", $string, PHP_EOL);

    // do some work
    sleep(1);

    // send reply back to client
    $responder->send("World");

}

print("program ended cleanly\n");