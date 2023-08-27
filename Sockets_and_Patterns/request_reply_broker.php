<?php

/**
 * simple request-reply broker
 */

//  prepare our context and sockets
$context = new ZMQContext();
$frontend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$frontend->bind("tcp://*:5559");

$backend = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
$backend->bind("tcp://*:5560");


// initialize poll set
$poll = new ZMQPoll();
$poll->add($frontend, ZMQ::POLL_IN);
$poll->add($backend, ZMQ::POLL_IN);

$readable = $writeable = array();


// switch messages between sockets
while (true) {
    $events = $poll->poll($readable, $writeable);
    var_dump($events);
var_dump($readable);

    foreach ($readable as $socket) {
        var_dump($socket === $frontend);
        var_dump($socket === $backend);
        echo '------'.PHP_EOL;
        if($socket === $frontend) {
            while (true){
                echo "Front recving...".PHP_EOL;
                $msg = $socket->recv();
                echo "Front recevied: [$msg]".PHP_EOL;
                // multipart detection
                $more = $socket->getSockOpt(ZMQ::SOCKOPT_RCVMORE);
                
                echo "sending from Backend".PHP_EOL;
                $backend->send($msg, $more ? ZMQ::MODE_SNDMORE : null);
                echo "sent from Backend".PHP_EOL;
                if (!$more) {
                    echo "END Of MSG".PHP_EOL;
                    break; // last message part
                }
            }
            echo "break WHILE".PHP_EOL;
            
        } else {
            echo "Back recving...".PHP_EOL;
            $msg = $socket->recv();
            echo "Back recevied: [$msg]".PHP_EOL;
            //multipart detection
            $more = $socket->getSockOpt(ZMQ::SOCKOPT_RCVMORE);
            
            $frontend->send($msg, $more ? ZMQ::MODE_SNDMORE : null);
            if (!$more) {
                break; //last message part
            }
        }
    }
}
