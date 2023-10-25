<?php
/**
 * lazy pirate server
 * biunds req socket to tcp://*:5555
 * like hwserver except:
 * - echoes request as is
 * randomly runs slowly, or exits to simulate a crash
 */


$context = new ZMQContext();
$server = new ZMQSocket($context, ZMQ::SOCKET_REP);
$server->bind("tcp://*:5555");

$cycles = 0;
while(true){
    $request = $server->recv();

    $cycles++;

    // simulate various problems, after a few cycles
    if($cycles>3 && rand(0,3)==0){
        echo "I: simulating a crash", PHP_EOL;
        break;
    }elseif($cycles >3 && rand(0,3) == 0){
        echo "I: simulating CPU overload", PHP_EOL;
        sleep(5);
        
    }

    printf("I: normal request (%s)%s", $request, PHP_EOL);

    sleep(1);
    $server->send($request);
}
