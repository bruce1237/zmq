<?php

/**
 * mdcliapi.h
 * 
 * majordomo protocol client api
 */

include_once "../zmsg.php";
include_once "../mdp.php";


class MDCli
{
    // structure of our class
    // we access these properties only via class methods
    private $broker;
    private $context;
    private $client;  // socket to broker
    private $verbose; // print activity to stdout
    private $timeout; // request timeout
    private $retries; // request retries

    public function __construct(string $broker, bool $verbose = false)
    {
        $this->broker = $broker;
        $this->context = new ZMQContext();
        $this->verbose = $verbose;
        $this->timeout = 2500;
        $this->retries = 3;
        $this->connect_to_broker();
    }

    protected function connect_to_broker()
    {
        $this->client = new ZMQSocket($this->context, ZMQ::SOCKET_REQ);
        $this->client->setSockOpt(ZMQ::SOCKOPT_LINGER, 0);
        $this->client->connect($this->broker);
        if ($this->verbose) {
            printf("I: connecting to broker at %s...", $this->broker);
        }
    }

    public function set_timeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function set_retries($retries)
    {
        $this->retries = $retries;
    }

    /**
     * send request to broker and get reply by hook or crook
     * takes ownership of request message and destroys it when sent
     * returns the reply message or NULL if there was no reply
     */
    public function send(string $service, Zmsg $request): Zmsg
    {
        // prefix request with protocol frames
        // frame1: "MDPCxy" (six bytes, MDP/Client)
        // frame2: service name (printable string)
        $request->push($service);
        $request->push(MDPC_CLIENT);
        if ($this->verbose) {
            printf("I: send request to '%s' service", $service);
            echo $request->__toString();
        }

        $retries_left = $this->retries;
        $read = $write = array();
        while ($retries_left) {
            $request->set_socket($this->client)->send();

            // poll socket for a reply, with timeout
            $poll = new ZMQPoll();
            $poll->add($this->client, ZMQ::POLL_IN);
            $events = $poll->poll($read, $write, $this->timeout);

            // if we got a reply, process it
            if ($events) {
                $request->recv();
                if ($this->verbose) {
                    echo "I: received reply: ", $request->__toString(), PHP_EOL;
                }

                // don't try to handle errors, just assert noisily
                assert($request->parts() >= 3);

                $header = $request->pop();
                assert($header == MDPC_CLIENT);

                $reply_service = $request->pop();
                assert($reply_service == $service);

                return $request; //success
            } elseif ($retries_left--) {
                if ($this->verbose) {
                    echo "W: no reply, reconnecting...", PHP_EOL;
                }

                // rec onnect, and resend msg
                $this->connect_to_broker();
                $request->send();
            } else {
                echo "W: permanent error, abandoning request", PHP_EOL;
                break; //give up
            }
        }
    }
}
