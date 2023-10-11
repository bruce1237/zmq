<?php

/**
 * broker peering simulation (part 1)
 * prototypes the state flow
 */

//  first argument is this broker's name
// other arguments are our peers' names

if ($_SERVER['argc'] < 2) {
    echo "syntax: peering1 me {your}...", PHP_EOL;
    exit();
}

$self = $_SERVER['argv'][1];
printf("I: preparing broker at %s...%s", $self, PHP_EOL);

// prepare our context and sockets
$context = new ZMQContext();

// bind statebe to endpoint
$statebe = $context->getSocket(ZMQ::SOCKET_PUB);
$endpoint = sprintf("ipc://%s-state.ipc", $self);
$statebe->bind($endpoint);

// connect statefe to all peers
$statefe = $context->getSocket(ZMQ::SOCKET_SUB);
$statefe->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "");

for ($argn = 2; $argn < $_SERVER['argc']; $argn++) {
    $peer = $_SERVER['argv'][$argn];
    printf("I: connecting to state backend at '%s' %s", $peer, PHP_EOL);
    $endpoint = sprintf("ipc://%s-state.ipc", $peer);
    $statefe->connect($endpoint);
}


$readable = $writeable = array();

// send out status messages to peers, and collect from peers
// the zmq_poll timeout defines our won heart beating
while (true) {
    // initialize poll set
    $poll = new ZMQPoll();
    $poll->add($statefe, ZMQ::POLL_IN);

    // poll for activity, or 1 second timeout
    $events = $poll->poll($readable, $writeable, 1000);

    if ($events > 0) {
        // handle incoming status message
        foreach ($readable as $socket) {
            $address = $socket->recv();
            $body = $socket->recv();
            printf("%s - %s workers free%s", $address, $body, PHP_EOL);
        }
    } else {
        // we stick our won address onto the envelop
        $statebe->send($self, ZMQ::MODE_SNDMORE);
        // send random value for worker availability
        $available_workers = mt_rand(1, 10);
        $statebe->send($available_workers);
        printf("send random value for worker availability(%s)%s",$available_workers, PHP_EOL);
    }
}

// we never get here
