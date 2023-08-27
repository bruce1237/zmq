<?php
/**
 *  task design 2
 * parallel task sink with kill signaling
 * adds pub-sub flow to send kill signal to workers
 */

 $context = new ZMQContext();
 
//  socket to receive messages on
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->bind("tcp://*:5558");

// socket for worker control
$controller = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$controller->bind("tcp://*:5559");


// wait for start of batch
$string = $receiver->recv();

// process 100 confirmations
$tstart = microtime(true);
$total_msec = 0; // total calculated cost in msecs

for ($task_nbr=0; $task_nbr<100; $task_nbr++){
    $string = $receiver->recv();

   echo "receiving task [$string]".PHP_EOL;
}

$tend = microtime(true);

$total_msec = ($tend - $tstart) * 1000;
echo PHP_EOL;

printf("total elapsed time: %d msec", $total_msec);

echo PHP_EOL;

echo "sending KILL".PHP_EOL;

$controller->send("KILL");

echo "going to sleep".PHP_EOL;
sleep(1);
