<?php

/**
 * majordomo protocol worker API
 */

use Symfony\Bundle\MakerBundle\Str;
use Twig\Node\Expression\Test\NullTest;

include_once "../zmsg.php";
include_once "../mdp.php";


// reliability parameters
define("HEARTBEAT_LIVENESS", 3);

class MDWrk
{
    // structure of our class
    // we access these properties only via class methods
    private $ctx; //cour context
    private $broker;
    private $service;
    private $worker;
    private $verbose = false;

    // heartbeat management
    private $heartbeat_at;  // when to send heartbeat
    private $liveness;      // how many attempts left
    private $heartbeat;     // heartbeat delay, msecs
    private $reconnect;     // reconnect delay, msecs

    // internal state
    private $expect_reply = 0;

    //return address, if any
    private $reply_to;

    public function __construct(String $broker, string $service, bool $verbose)
    {
        $this->ctx = new ZMQContext();
        $this->broker = $broker;
        $this->service = $service;
        $this->verbose = $verbose;
        $this->heartbeat = 2500;
        $this->reconnect = 2500;
        $this->connect_to_broker();
    }

    // send message to broker
    // if no msg is provided, creates one internally
    public function send_to_broker(string $command, ?string $option, Zmsg $msg = null)
    {
        $msg = $msg ? $msg : new Zmsg();

        if ($option) {
            $msg->push($option);
        }

        $msg->push($command);
        $msg->push(MDPW_WORKER);
        $msg->push("");


        if ($this->verbose) {
            printf("I: sending %s to broker %s", $command, PHP_EOL);
            echo $msg->__toString();
        }
        $msg->set_socket($this->worker)->send();
    }

    // connect to broker
    public function connect_to_broker()
    {
        $this->worker = new ZMQSocket($this->ctx, ZMQ::SOCKET_DEALER);
        $this->worker->connect($this->broker);
        if ($this->verbose) {
            printf("I: connecting to broker at %s....%s", $this->broker, PHP_EOL);
        }

        // register service with broker
        $this->send_to_broker(MDPW_READY, $this->service, Null);

        // if liveness hits zero, queue is considered disconnected
        $this->liveness = HEARTBEAT_INTERVAL;
        $this->heartbeat_at = microtime(true) + ($this->heartbeat / 1000);
    }

    // set heartbeat delay
    public function set_heartbeat($heartbeat)
    {
        $this->heartbeat = $heartbeat;
    }

    public function set_reconnect($reconnect)
    {
        $this->reconnect = $reconnect;
    }

    // send reply, if any, to broker and wait for next request.
    public function recv(Zmsg $reply = null)
    {
        // format and send the reply if we were provided one
         assert($reply || !$this->expect_reply);
         if($reply){
            $reply->wrap($this->reply_to);
            $this->send_to_broker(MDPW_REPLY, NULL, $reply);
         }

         $this->expect_reply = true;
         
         $read = $write = array();
         while(true){
            $poll = new ZMQPoll();
            $poll->add($this->worker, ZMQ::POLL_IN);

            $events = $poll->poll($read, $write, $this->heartbeat);

            if($events){
                $zmsg = new Zmsg($this->worker);
                $zmsg->recv();

                if($this->verbose){
                    echo "I: received message form broker:", PHP_EOL;
                    echo $zmsg->__toString();
                }

                $this->liveness = HEARTBEAT_LIVENESS;
                
                // don't try to handle errors, just assert noisily
                assert($zmsg->parts()>=3);
                
                $zmsg->pop();
                $header = $zmsg->pop();
                assert($header == MDPW_WORKER);

                $command = $zmsg->pop();
                
                if($command == MDPW_REQUEST){
                    // we should pop and save as many addresses as there are
                    // up to a null part, but for now just save as one...
                    $this->reply_to = $zmsg->unwrap();

                    return $zmsg;       // we have a request to process
                }elseif($command == MDPW_HEARTBEAT){
                    $this->connect_to_broker();
                }else{
                    echo "E: invalid input message", PHP_EOL;
                    echo $zmsg->__toString();
                }
            }elseif(--$this->liveness == 0){
                // poll ended on timeout, $event being false
                if($this->verbose){
                    echo "W: disconnected from broker - retrying....", PHP_EOL;
                }
                usleep($this->reconnect*1000);
                $this->connect_to_broker();
            }

            // send heartbeat if it's time
            if(microtime(true)> $this->heartbeat_at){
                $this->send_to_broker(MDPW_HEARTBEAT, NULL, null);
                $this->heartbeat= microtime(true) + ($this->heartbeat/1000);
            }
         }
    }
}
