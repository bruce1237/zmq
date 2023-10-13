<?php
$context = new ZMQContext();
$client = $context->getSocket(ZMQ::SOCKET_REQ);
$client->connect("ipc://brokerC.ipc");

$client->send("HELLO");
echo "send HELLO\n";


for ($i=0; $i<10; $i++){
    echo "seding...\n";
    $result = $client->recv();
    printf("got result from worker:%s%s",$result, PHP_EOL);


    $client->send("task: {$i}");
    printf("sending task%s%s", $i, PHP_EOL);

    

}