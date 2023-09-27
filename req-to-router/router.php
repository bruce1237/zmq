<?php
$context = new ZMQContext();
$router = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$router->bind("tcp://*:1234");

while(true){
    
    $req_addr = $router->recv();
    echo "recevied: REQ_ADDR: ".$req_addr.PHP_EOL;
    $delimiter = $router->recv();
    echo "recevied: Delimiter: ".$delimiter.PHP_EOL;
    $msg = $router->recv();
    echo "recevied: MSG: ".$msg.PHP_EOL;
    // $nothing = $router->recv();
    // echo "recevied: nothing of END OF MSG: ".$nothing.PHP_EOL;
    
    echo "add REQ address to frame\n";
    $router->send($req_addr, ZMQ::MODE_SNDMORE);
    
    echo "add delimiter\n";
    $router->send("", ZMQ::MODE_SNDMORE);
    
    echo "send reply\n";
    $router->send("hello REQ: $msg \n");
}