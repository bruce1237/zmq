<?php
/**
 * It collects the 100 tasks, 
 * then calculates how long the overall processing took, 
 * so we can confirm that the workers really were running 
 * in parallel if there are more than one of them
 */
$context = new ZMQContext();
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->bind("tcp://*:5558");

$string = $receiver->recv();
echo "UNKNOW {$string}".PHP_EOL;
$results = [];
$tstart = microtime(true);

$total_mesc = 0;
for ($task_nbr = 0; $task_nbr < 100; $task_nbr++) {
    $result = $receiver->recv();
    echo "collecting result".PHP_EOL;
    $results[] = $result;
    
}

$tend = microtime(true);

$total_mesc = ($tend - $tstart) * 1000;

echo PHP_EOL;
printf("Total elapsed time: %d mesc", $total_mesc);
echo PHP_EOL;
var_dump($results);