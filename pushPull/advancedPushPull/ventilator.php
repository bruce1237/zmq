<?php
/**
 * It generates 100 tasks, 
 * each a message telling the worker 
 * to sleep for some number of milliseconds
 */
$context =new ZMQContext();

//Socket to send message on

$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->bind("tcp://*:5557");


echo "Press enter when the workers are ready: ";
$fp = fopen('php://stdin', 'r');
$line =fgets($fp, 512);
fclose($fp);
echo "sending tasks to workers...", PHP_EOL;

$sender->send(0);

$total_mesc = 0;
for ($task_nbr = 0; $task_nbr < 100; $task_nbr++) {
    $workload = mt_rand(1, 100);
    $total_mesc += $workload;
    echo "send task: {$workload}".PHP_EOL;
    $sender->send($workload);
}

printf("Total expected cost: %d mesc\n", $total_mesc);
sleep(1);