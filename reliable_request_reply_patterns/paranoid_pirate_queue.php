<?php

/**
 * paranoid pirate queue
 */


include "../zmsg.php";
define("MAX_WORKERS", 100);
define("HEARTBEAT_LIVENESS", 3);
define("HEARTBEAT_INTERVAL", 1);



class Queue_T implements Iterator
{
    private $queue = array();

    // iterator function
    public function rewind(): void
    {
        return reset($this->queue);
    }

    public function value()
    {
        return current($this->queue);
    }

    public function key()
    {
        return key($this->queue);
    }

    public function next(): void
    {
        return next($this->queue);
    }
    public function current(): mixed
    {
        return current($this->queue);
    }

    /**
     * insert worker at end of queue, rest expiry
     * worker must not already be in queue
     */

    public function s_worker_append($identity)
    {
        if (isset($this->queue[$identity])) {
            printf("E: duplicate worker identity %s", $identity);
        } else {
            $this->queue[$identity] = microtime(true) + HEARTBEAT_INTERVAL * HEARTBEAT_LIVENESS;
        }
    }

    /**
     * remove worker from queue if present
     */
    public function s_worker_delete($identity)
    {
        unset($this->queue[$identity]);
    }

    /**
     * rest worker expiry, worker must be present
     */
    public function s_worker_refresh($identity)
    {
        if (!isset($this->queue[$identity])) {
            printf("E: worker %s not ready\n", $identity);
        } else {
            $this->queue[$identity] = microtime(true) + HEARTBEAT_INTERVAL * HEARTBEAT_LIVENESS;
        }
    }

    /**
     * pop next available worker off queue, return identity
     */
    public function s_worker_dequeue()
    {
        reset($this->queue);
        $identity = key($this->queue);
        unset($this->queue[$identity]);
        return $identity;
    }

    /**
     * look for & kill expired worker
     */
    public function s_queue_purge()
    {
        foreach ($this->queue as $id => $expiry) {
            if (microtime(true) > $expiry) {
                unset($this->queue[$id]);
            }
        }
    }

    /**
     * return the size of the queue
     */
    public function size()
    {
        return count($this->queue);
    }
}

// prepare context and sockets

// frontend for client
$context = new ZMQContext();
$frontend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$frontend->bind("tcp://*:5555");

// backend for worker
$backend = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$backend->bind("tcp://*:5556");

$read = $write = array();

// queue of available workers
$queue = new Queue_T();

//  send out heartbeats at regular intervals
$heartbeat_at = microtime(true) + HEARTBEAT_INTERVAL;


while(true){
    $poll = new ZMQPoll();
    $poll->add($backend, ZMQ::POLL_IN);

    // poll frontend only if we have available workers
    if($queue->size()){
        $poll->add($frontend, ZMQ::POLL_IN);
    }

    $events = $poll->poll($read, $write, HEARTBEAT_INTERVAL*1000); //milliseconds
    
    if($events>0){
        foreach($read as $socket){
            $zmsg = new Zmsg($socket);

            // handle worker activity on backend
            if($socket === $backend){
                $identity = $zmsg->unwrap();

                // return reply to client if it's not a control message
                if($zmsg->parts() ==1){
                    if($zmsg->address() == "READY"){
                        $queue->s_worker_delete($identity);
                        $queue->s_worker_append($identity);
                    }elseif ($zmsg->address()=="HEARTBEAT"){
                        $queue->s_worker_refresh($identity);
                    }else{
                        printf("E: invalid message from %s%s%s", $identity, PHP_EOL, $zmsg->__toString());
                    }
                }else{
                    $zmsg->set_socket($frontend)->send();
                    $queue->s_worker_append($identity);
                }
            }else{
                // now get next client request, route to next worker
                $identity = $queue->s_worker_dequeue();
                $zmsg->wrap($identity);
                $zmsg->set_socket($backend)->send();
            }
        }

        if(microtime(true) > $heartbeat_at){
            foreach($queue as $id => $expiry){
                $zmsg = new Zmsg($backend);
                $zmsg->body_set("HEARTBEAT");
                $zmsg->wrap($id, null);
                $zmsg->send();
            }
            $heartbeat_at = microtime(true) + HEARTBEAT_INTERVAL;
        }
        $queue->s_queue_purge();
    }
}