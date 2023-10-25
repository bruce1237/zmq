<?php
/**
 * lazy pirate client
 * use zmq_poll to do a safe request-reply
 * to run, start lpserver and then randomly kill/restart it
 */


define("REQUEST_TIMEOUT", 2500);
define("REQUEST_RETRIES",3);


/**
 * helper function that returns a new configured socket connected to the hello world server
 */

function client_socket(ZMQContext $context){
    echo "I: connecting to server...", PHP_EOL;
    $client = new ZMQSocket($context, ZMQ::SOCKET_REQ);
    $client->connect("tcp://localhost:5555");

    // configure socket to not wait at close time
    $client->setSockOpt(ZMQ::SOCKOPT_LINGER, 0);

    return $client;
}

$context = new ZMQContext();
$client = client_socket($context);

$sequence = 0;
$retries_left = REQUEST_RETRIES;
$read = $write = array();

while($retries_left){
    // we send a request, then we work to get a reply
    $client->send(++$sequence);

    $expect_reply = true;
    while($expect_reply){
        // poll socket for a reply, with timeout
        $poll = new ZMQPoll();
        $poll->add($client, ZMQ::POLL_IN);
        $events = $poll->poll($read, $write, REQUEST_TIMEOUT);

        // if we got a reply, process it
        if($events>0){
            // we got a reply from the server, must match sequence
            $reply = $client->recv();

            if(intval($reply) ==  $sequence){
                printf("I: server replied OK (%s)%s", $reply, PHP_EOL);

                $retries_left = REQUEST_RETRIES;
                $expect_reply = false;
            }else{
                printf("E: server sems to be offline, abandoning%s", PHP_EOL);

            }
        }elseif(--$retries_left ==0){
            echo "E: server seems to be offline, abandoning", PHP_EOL;
            break;
        }else{
            echo "W: no response from server, retrying ...", PHP_EOL;
            // old socket will be confused; close it and open a new one
            $client = client_socket($context);
            // send request again, on new socket
            $client->send($sequence);
        }

    }




}