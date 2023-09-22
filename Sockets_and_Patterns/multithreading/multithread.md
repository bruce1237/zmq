## Multithreading with ZMQ

```php
<?php
/**
 * multithreaded Hello world server, uses process ue to php's lack of threads
 */

function worker_routine()
{
    $context = new ZMQContext();
    // socket to talk to dispatcher

    $receiver = new ZMQSocket($context, ZMQ::SOCKET_REP);
    $receiver->connect("ipc://workers.ipc");

    while(true) {
        $string = $receiver->recv();
        printf("Received request: [%s]%s", $string, PHP_EOL);

        // do some work
        sleep(1);

        // send reply back to client
        $receiver->send("World");
    }
}

// launch pool of worker threads
for ($thread_nbr = 0; $thread_nbr <5; $thread_nbr++){
    $pid = pcntl_fork();
    if($pid == 0){
        worker_routine();
        exit();
    }
}

// prepare our conext and sockets
$context = new ZMQContext();

// socket to talk to clients
$clients = new ZMQSocket($context, ZMQ::SOCKET_ROUTER);
$clients->bind("tcp://*:5555");

// socket to talk to workers
$workers = new ZMQSocket($context, ZMQ::SOCKET_DEALER);
$workers->bind("ipc://workers.ipc");

// connect work threads to client threads via a queue
$device = new ZMQDevice($clients, $workers);
$device->run();
```

![Alt text](<Screenshot 2023-09-20 at 21.50.40.png>)

### how it works

- the server starts a set of worker threads. Each worker thread creates a REP socket and then processes requests on this socket. worker threads are just like single-threaded servers. the only differences are the transport(inproc instead of tcp), and the bind-connect direction.
- the server creates a ROUTER socket to talk to clients and binds this to its external interface(over TCP)
- the server creates a DEALER socket to talk to the workers and bind this to its internal interface(over inproc)
- the server starts a proxy that connects to two sockets. the proxy pulls incoming request fairly from all clients, and distributes those out to workers. it also routes replies back to their origin.


## replay
```php
<?php

/**
 * multithreaded repay, actually using processes due a lack of php threads
 */

function step1()
{
    $context = new ZMQContext();
    // signal downstream to step 2
    $sender = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
    $sender->connect("ipc://step2.ipc");
    $sender->send("");
}

function step2()
{
    $pid = pcntl_fork();
    if ($pid == 0) {
        step1();
        exit();
    }

    $context = new ZMQContext();
    // bind to ipc: endpoint, then start upstream thread
    $receiver = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
    $receiver->bind("ipc://step2.ipc");

    // wait for signal
    $receiver->recv();

    // singal downstream to step 3
    $sender = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
    $sender->connect("ipc://step3.ipc");
    $sender->send("");
}

// start upstream thread then bind to icp: endpoint
$pid = pcntl_fork();
if ($pid == 0) {
    step2();
    exit();
}
$context = new ZMQContext();
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PAIR);
$receiver->bind("ipc://step3.ipc");

// wait for signal
$receiver->recv();

echo "test Successful!" . PHP_EOL;‹
```
![Alt text](<Screenshot 2023-09-20 at 22.32.28.png>)

### how ti works
this is a classic pattern for multithreading with ZMQ:
1. two threads communicate over inproc, using a shared context.
2. the parent thread creates one sockets, binds it to an inproc:@<*>@endpoint, and *then// starts the chid thread, passing the context to it
3. the child thread creates the second socket, connects it to that inproc:@<*>@endpoint, and *then// signals to the parent thread that it's ready

note that multithreading code using this pattern is not scalable out to processses. if you use inproc and socket pairs, you are building a tightly-bound application. i.e., one where your threads are structurally interdependent. do this when low latency is reeally vital. the other design pattern is a loosely bound applicaiton, where threads have their own context and communicate over ipc or tcp. you can easily break loosely bound threads into separate processes.

this is the first time we've shown an example using PAIR sockets. why using PAIR? other socket combinations might seem to work, but they all have side effects that could interfere with signaling:
- you can use PUSH fro the sender and PULL for the receiver. this looks simple and will work, but remember that PUSH will distribute message to all available receivers. if  you by accident start two receiver(e.g., you already have one running and you start a second), you'll lose half of your signals. PAIR has the advantage of refusing more than one connection; the air is exclusive
- you can use DEALER for the sender and ROUTER for the receiver. ROUTER, however, wraps your message in an "envelope", meaning you zero-size signal turns into a multipart message. if you don't care about the data and treat anything as a valid signal, and if you don't read more than once from the socket, that won't matter. if however, you decide to send real data, you will suddenly find ROUTER providing your with "wrong" messages. DEALER also distributes outgoing messages, giving the same risk as PUSH
- you can use PUB and the sender and SUB for the receiver, this will correctly deliver your messages exactly as you sned them and PUB does not distribute as PUSH or DEALSER do , however, you need to configure the subscriber with an empty subscription, which is annoying.

for these reasons, PAIR makes the best choice for coordination between paris of threads.

