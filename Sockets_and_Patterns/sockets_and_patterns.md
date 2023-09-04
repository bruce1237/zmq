# The Socket API
life cycle
1. creating and destoying socktes, which go together to from a karmic circle of socket life `zmq_socket()` and `zmq_close()`
2. configuring sockets by setting options on them and checking them if necessary(`zmqq_setsockopt()` `zmq_getsocketopt()`)
3. plugging sockets into the network topology by creating ±MQ connections to and from them (`zmq_bind()`, `zmq_connect()`)
4. using the sockets to carry data by writing and receiving messages on them (`zmq_msg_send()`, `zmq_msg_recv()`)

## plugging sockts into the topology
to create a connection between two nodes, you use `zmq_bind()` in one node and `zmq_connect()` in the other. as a general rule of thumb, the node that does `zmq_bind()` is a "server", sitting on a well-know network address, and the node which does `zmq_connect()` is a "client", with unknown or arbitrary network addresses.  thus we say that we bind a socket to an endpoint and connect a socket to an endpoint. the endopoint being that well-known network address.

zmq connections are somewhat different from classic TCP connections
- the go across an arbitrary transport(inpro, ipc, tcp , pgm, epgm) (`zmq_inproc()`, `zmq_ipc`,`zmq_tcp()`, `zmq_pgm()`, `zmq_epgm()`)
- one socket may have many outoging and many incoming connections
- there is no `zmq_accept()` method, when a socket is bound to an endpoint it automatically starts accepting connects
- the network connection itself happens in the backgroud and zmq will automatically reconnect if the network connection is broken
- you application code can not work with these connections directly, the are encapsulated under the socket

## sending and receiving messages
to send and receive messages using the `zmq_msg_send()` and `zmq_msg_recv()` methods.

### main differences between TCP sockets and zmq sockets when it coms to working with data
- zmq sockets carry messages like UDP, rather than a stream of bytes as TCP does. a zmq message is length-specified binary data. their design is optimized for performance and so a little tricky
- zmq socket do their I/O in a background thread. this means that messages arrive in local input queues and are sent from local output quesues, no matter what your application is busy doing
- zmq sockets have one-to-N routing behavior built-in, according to the socket type.

the `zmq_send()` method does not actually send the message tot he socket connections. it queues the message to so that the I/O thread can send it asynchronously. it does not block except in some exception cases. so the message is not necessarily sent when `zmq_send()` returns to your application.


### unicast transports
zmq provides a set of unicast transports (inproc, ipc, and tcp) and multicast transports (epgm, pgm). multicast is an advanced technique that we'll come to later. 

for most common cases, use tcp, which is a disconnected TCP transport. it is elastic, protable, and fast enough for most cases. we call this disconnected because zmq tcp transport doesn't require that the endpoint exists before you connect to it. clients and servers can connect and bind at any time, can go and come back, and it remains transparent to applications.

the  inter-process ipc transport is disconnected, like tcp. it has one limitation: it does not yet work on windoes. bny convention we use endpoint name with an `.ipc` extension to avoid potential conflict with other file name. in UNIX, if use ipc endpoints you need to create these with appropriate permissions otherwises they may not be shareable tetween
processes running under different user IDs you must also make sure all processes can access the files e.g., by running int he same working directory.

the `inproc` inter-thread transport, is a connected signaling transport. it is much faster than tcp or ipc. this transport has a specific limition compare to tcp and ipc: the server must issue a bind before any client issues a connect. this was fixed in zme v4 and later

## I/O threads
zmq does I/O in a background thread, one I/O thread (for all sockets) is sufficient for all but the most extreme applications. when you create a new context, it starts with one I/O thread. the general rule is to allow one I/O thread per gigabyte of data in or out per second. to rrraise the number of I/O thread, use `zmq_ctx_set()` call before creating any sockets.

we've seen that one socket can handle dozens even thousands of connections at once. a traditional networked application has one process or one thread per remote connection, and that process or thread handles one socket. zmq lets you collapes this entire structure into a single process and then break  it up as necessary for scaling.

if you using zmq for inter-thread communications only, (i.e., a multithreaded application that does no external socket I/O) you can set the I/O threads to zero. ti's not a significant optimization though, more of a curiosity.

## Patterns
zmq patterns are implemented by pairs of sockets with matching types. in other workds, to understand zmq patterns you need to understand socket types and how thy work together. mostly, this just taks study; there is litttle that is obvious at this level

the built-in core zmq patterns are:
- `request-reply`, which connects a set of clients to a set of services. this is a remote procedure call and task distribution pattern
- `Pub-sub`, which connects a set of publishers to a set of subscribers. this is a data distribution pattern
- `Pipeline`, which connects nodes in a fan-out/fan-in pattern that can have multiple steps and loops. this is a parallel task distribution and collection pattern
- `Exclusive pair`, which connects two sockets exclusively. this is a pattern for connecting tow threads in a process, not to be confused with "normal" parir of sockets.

list os socket combinations that are valid for a connect-bind pair(either side can bind)

- Pub and Sub
- REQ and REP
- REQ and ROUTER(take care, REQ inserts an extra null frame)
- DEALER and REP(take care, REP assumes a null frame)
- DEALER and ROUTER
- DEALER and DEALER
- ROUTER and ROUTER
- PUSH and PULL
- PAIR and PAIR

you'll also see references to XPUB and XSUB sockets, which like raw versions of PUB and SUB. any other combination will produce undocumneted and unreliable results and may return error in future versions of zmq. you can and will, of course, bridge other socket types via code. i.e., read from one socket type and write to another.

### issue with zmq_recv()
`zmq_recv` is bad at dealing with arbitrary message sizes: it truncates messages to whatever buffer size you provide. so there's a second API that works with `zmq_msg_t` structure, with a richer but more difficult API:
- initialise a message: `zmq_msg_init()`, `zmq_msg_init_size()`, `zmq_msg_init_data()`
- sending and receiving a message: `zmq_msg_send()`, `zmq_msg_recv()`
- release a message: `zmq_msg_close()`
- access message content: `zmq_msg_data()`, `zmq_msg_size()`, `zmq_msg_more()`
- work with message properties: `zmq_msg_get()`, `zmq_msg_set()`
- message manipulation: `zmq_msg_copy()`, `zmq_msg_move()`


**On the wire** zmq messages are blobs of any size from zero upwards that fit in memory. you do you won serialization using protocol buffers, msgpack, JSON or whatever else your applications need to speak. it's wise to choose a data representation that is protable, but you can make your own decisions about trade-offs.

**In memory** zmq messages are `zmq_msg_t` structures( or classes depending on your language). here are the basic ground rules for  using zmq in c
1. You create and pass around zmq_msg_t objects, not blocks of data.

2. To read a message, you use zmq_msg_init() to create an empty message, and then you pass that to zmq_msg_recv().

3. To write a message from new data, you use zmq_msg_init_size() to create a message and at the same time allocate a block of data of some size. You then fill that data using memcpy, and pass the message to zmq_msg_send().

4. To release (not destroy) a message, you call zmq_msg_close(). This drops a reference, and eventually ZeroMQ will destroy the message.

5. To access the message content, you use zmq_msg_data(). To know how much data the message contains, use zmq_msg_size().

6. Do not use zmq_msg_move(), zmq_msg_copy(), or zmq_msg_init_data() unless you read the man pages and know precisely why you need these.

7. After you pass a message to zmq_msg_send(), ØMQ will clear the message, i.e., set the size to zero. You cannot send the same message twice, and you cannot access the message data after sending it.

8. These rules don’t apply if you use zmq_send() and zmq_recv(), to which you pass byte arrays, not message structures.




## handling multiple Sockets
in all the examples so far, the main loop of most examples has been:
1. wait for message on socket
2. process message
3. repeat

example of a dirty hack.
```php
<?php
/**
 * reading from multiple sockets
 * using a simple recv loop
 */

 $context = new ZMQContext();
 $receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
 $receiver->connect("tcp://localhost:5557");

//  connect to weather station
 $subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
 $subscriber->connect("tcp://localhost:5556");
 $subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "10001");

//  process messages from both sockets
// we prioritize traffic from the task ventilator
while(true){
    try{
        for ($rc = 0; !$rc;){
            if($rc = $receiver->recv(ZMQ::MODE_NOBLOCK)){
                echo "process task";
            }
        }
    } catch(ZMQSocketException $e){
        echo $e->getMessage();
    }

    try {
        for ($rc = 0; !$rc;){
            if ($rc = $subscriber->recv(ZMQ::MODE_NOBLOCK)){
                echo "process weather update";
            }
        }
    } catch(ZMQSocketException $e){
        echo $e->getMessage();
    }
    usleep(1);
}
```
issues with the dirty hack is additional latency on the first msg and sleep at the end.

the correct way is to use zmq_poll
```php
<?php

/**
 * reading from multiple sockets by zmq_poll
 */

$context = new ZMQContext();

//  connect to ventilator
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->connect("tcp://localhost:5557");

// connect to weather server
$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$subscriber->connect("tcp://localhost:5556");
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "10001");


//initialize poll set
$poll = new ZMQPoll();
$poll->add($receiver, ZMQ::POLL_IN);
$poll->add($subscriber, ZMQ::POLL_IN);


$readable = $writable = array();


while (true) {
    $events = $poll->poll($readable, $writable);
    if($events > 0){
        foreach($readable as $socket){
            if($socket === $receiver){
                $msg = $socket->recv();
                echo "received Rec msg: $msg".PHP_EOL;
            } elseif ($socket === $writable) {
                $msg = $socket->recv();
                echo "received WRT msg: $msg".PHP_EOL;
            }
        }
    }
}
```

the items structure has there four members:
```c
typedef struct {
    void *socket; // zmq socket to poll on
    int fd;       // or, native file handle to poll on
    short events  // events to poll on
    short revents; // events returned after poll
} zmq_pollitem_t;
```

## Multipart messages
 zmq lets us compose a message out of several frames, giving us a nultipart message. realistic applications use multipart messages heavily, both for wrapping messages with address information and for simple serialization. will look at reply envelopes later.


about multipart messages:
- when you send a multipart message, the first part(and all following parts) are only actually sent on the wire when you send the final part
- if you are using `zmp_poll()`, when y ou receive the first part of a message, all the rest has also arrived
- you will receive ALL part of a message, or NONE at all
- each part of a message is a separate `zmq_msg` item
- you will receive all parts of a message whether or not you check the more property
- on sending, zmq queues message frames in memory until the last is received, then send them all.
- there is no way to cancel a partially sent message, except by closing the socket.


## Intermediaries and Proxies

## The Dynamic discovery  problem

one of the problem you will hit as you design larger distributed architectures is discovery. that is, how do pieces know about each other? it's especially difficult if pieces come and go, so we call this the "dynamic discovery problem"

there  are several solutions to dynamic discovery. the simplest is to entirely avoid it by hardcoding or configuring the network architecture so discovery is done by hand. that is, when you add a new piece, you reconfigure the network to know about it.

> small-scal pub-sub network
                        Publisher
                        ---------
                        PUB
                        |
    ______________________________________________              
    |                |              |            |
   Sub             Sub             Sub         Sub
  Subscriber      Subscriber    Subscriber   Subscriber

in practice, this leads to increasingly fragile and unwieldy architectures. let's say you have one publisher and a hundred subscribers. you connect each subscriber to the publisher by configuring a publisher endpoint in each subscirber. that's easy, subcribers are dynamic; the publiher is static. now say you add more publishers. suddenly, it's not so easy any more. if you continue to connect each subscriber to each publisher, the cost of avoiding dynamic discovery gets higher and higher.

> pub-sub network with proxy

 Publisher      Publisher       Publisher       Publisher
 ---------      ---------       ---------       ---------
   PUB             PUB             PUB             PUB
    |               |               |               |
    |(connect)      |(connect)      |(connect)      |(connect) 
    |               |               |               |
    _________________________________________________
                            |
                            | bind
                            |
                        ----------    
                        |  XSUB  |
                        ----------
                        |  Proxy |
                        ----------
                        |  XPUB  |
                        ----------
                            |
                            |(bind)
                            |
    _________________________________________________                     |               |               |               |
    |(connect)      |(connect)      |(connect)      |(connect) 
    |               |               |               |       
    SUB             SUB             SUB             SUB
  Subscirber    Subscirber      Subscirber      Subscirber


there are quite a few answers to this, but the very simplest answer is to add an intermediary, that is, a static point in the network to which all other nodes connect. in classic messaging, this is the job of the message broker. zmq doesn't come with a message broker as such, but it lets us build intermediaries quite easily.

you might wonder, if all networks eventually get large enough to need intermediaries, why don't we simply have a messge broker in place for all applications? for beginners, ti's a fiar compromise. just always use a star topology, forget about performance, and things will usually work. however, message brokers are greedy things; in their role as central intermediaries, they become too complex, too stateful and eventually a problem 

it's better to think of intermediaries as simple stateless message switches, a good analogy is an HTTP proxy; it's there, but doesn't have any special role. adding a pub-sub proxy solves the dynamic discovery problem in our example, we set the proxy in the middle of the network. the proxy opens an `XSUB` socket and `XPUB` socket, and binds each to well-know IP address oand prots. then all other processes connect to the proxy, instead of to each other. ti becomes trivial to add more subscribers or publishers.

> Extended pub-sub

 Publisher      Publisher       Publisher       Publisher
 ---------      ---------       ---------       ---------
   PUB             PUB             PUB             PUB
    |               |               |               |
    |(connect)      |(connect)      |(connect)      |(connect) 
    |               |               |               |
    _________________________________________________
                            |
                            | bind
                            |
                        ----------    
                        |  XSUB  |
                        ----------
                        |**CODE** |
                        ----------
                        |  XPUB  |
                        ----------
                            |
                            |(bind)
                            |
    _________________________________________________                     
    |               |               |               |
    |(connect)      |(connect)      |(connect)      |(connect) 
    |               |               |               |       
    SUB             SUB             SUB             SUB
  Subscirber    Subscirber      Subscirber      Subscirber

we need `XPUB` and `XSUB` sockets becauses zmq does subscirption forwarding from subscribers to publishers. XSUB and XPUB are exactly like SUB and PUB except they expose subscription as special messages. the proxy has to forward these subscription messages from subscriber side to publisher side. by reading them from the XPUB socket and writing them to the XSUB socket. this is the main use case for XSUB and XPUB.


## shared Queue( DEALER and ROUTER sockets)
in t he hellow world client/server application, we have one client that talks to one server. however, in real cases we usually need to allow multiple services as well as multiple clients. this lets us scale up the power of the service(many threads or processes or nodes rather than just one). the only constraint is that services must be stateless, all state being in the request or in some shared storage such as database

> Request Distribution

                        Client
                         REQ
                         |
                         |(R1, R2, R3, R4)
                         |
            ---------------------------------
            |               |               |
            |R1, R4         |R2             |R3
            |               |               |
           REP             REP             REP
        ServiceA        ServiceB        ServiceC


there are tow ways to connect multiple clients to multiple servers. the brute force way is to connect each socket to multiple service endpoints. one client socket can connect to multiple service sockets, and the REQ socket will then distribute requests among these service. let's say you connect a client socket to three service endpoints: A, B and C, the client makes request R1, R2, R3, R4. R1 and R4 go to service A, R2 goes to B, and R3 goes to service C


this deisgn lets you add more clients cheaply. you can also add more services, each client will distribute its requests to the services. but each client has to know the service topology. if you have 100 clients and ten you ecide to add three more services, you need to reconfigure and restart 100 clients in order for the clients to know about the three new services.

that's clearly not the kind of thing we want ot be ding at 3 am. when our supercomputing cluster has run out of resources and we desperately need to add a couple of hundred of new service nodes, too many static pieces are like liquid concrete: knowledge is distrbuted and the more static pieces you have, the more effort it is to change the topology. what we want is something sitting in between clients and services that centralizes all knowledge of the topology. ideally, we should be able to add and remove serfvices or clients at any time without touching an y other part of the topology.

so we'll wirte a little message queuing broker that gives us this flexibiligy. the broker binds to two endpoins, a fronted for clients and a backedn for services. it then uses `zmq_poll()` to monitor these tow sockets for activity and when it has some , it shuttles messages between its two socktes. it doen't actually manage any queues explicitly -zmq does that automatically on 4each socket. 

when you user REQ to talk to REP, you get a strictly synchronous request-reply dialog. the client sends a request. the service reads the request and send s a reply. the client then reads the reply. if either the client or the service try to do anything else( sending tow reqquests in a row without waiting for response), they will get an error.

but our broker has to be nonblocking, obviously, we can u se `zmq_poll()` to wait for activity on either socket, but we can't use REP and REQ.


> Extended Request-Reply

   REQ             REQ             REQ             REQ
    |               |               |               |
    _________________________________________________
                            |
                            | 
                            |
                        ----------    
                        | ROUTER |
                        ----------
                        |**CODE**|
                        ----------
                        | DEALER |
                        ----------
                            |
                            |
    _________________________________________________                     
    |               |               |               |       
    REP            REP             REP             REP

luckily, there are two sockets called DEALER and ROUTER that let you do nonblocking requrest-response. you'll see later on how DEALER and ROUTER sockets let you build all kinds of asynchronous request-reply flows. for now, we 're just going to see how DEALER and ROUGER let us extend REQ-REP across an intermediary, that is, our little broker.

in this simple extended request-reply pattern, `REQ` talks to `ROUTER` and `DEALER` talks to `REP`. in between the DEALER and ROUTER, we have to have code(like our broker) that pulls messages off the one socket and shoves them  onto the other.

the request-reply broker binds to two endpoints, one for clients to connect to (the frontend socket) and one for workers to connect to (the backend) to test this broker, you will want to change your workers so they connect to the backend socket. *see request_reply_client.php, request_reply_worker.php, request_reply_broker.php*  **currently not working, not don't why yet...(the object id not match) the subscriber need ZMQ::SOCKOPT_SUBSCRIBE set up(?)**

## zmq Built-in Proxy function

it turns out the core loop in the previous section's rrbroker is very useful, and resusable. it lets us build pub-sub forwarders and shared queues and other little intermediaries with very little effort. zmq warps this up in a single method `zmq_proxy()`

`zmq_proxy(frontend, backend, capture);`

the two(or three sockets, if we want to capture data) must be properly connected, bound, and configured. when  we call the `zmq_proxy` method, it's exactly like starting the main loop of rrbroker. le's rewrite the request-reply broker to call zmq_proxy, and re-badge this as an expensive-sounding "message queue" (people have chared houses for code that di less):
```php
<?php
/**
 * msgqueue.php
 * simple message queuing broker
 * same as request-reply broker but using QUEUE device
 */

 $context = new ZMQContext();

//  socket facing clients
 $frontend = $context->getSocket(ZMQ::SOCKET_ROUTER);
 $frontend->bind("tcp://*:5559");

//  socket facing service
$backend = $context->getSocket(ZMQ::SOCKET_DEALER);
$backend->bind("tcp://*:55560");

// start built-in device
$device = new ZMQDevice($frontend, $backend);
$device->run();
```

if you're like most zmq users, at this stage your mind is starting to think, what kind of evil stuff can I do if I pulg random socket types into the proxy?  the short answer is: try it and work out what is happening, in practice, you would usually stick to ROUTER/DEALER, XSUB/XPUB, or PULL/PUSH.


### transport bridging
a frequent request from zmq users is "how do i connect my zmq network with technology X?" where X is some other networking or messaging technology.

> Pub-Sub forwarder proxy


                        Publisher
                           PUB
                bind tcp://localhost:5556
                            |
                            |
    --------------------------------------------
    |              |                           |
   SUB            SUB                         XSUB
                                             Proxy
                                             XPUB
   internal  network                          |
   __________________________________________ |
    External network                          |
                                      bind: tcp://10.1.1.1:8100
                                             |
                                             |
                                   ----------------------
                                   |                    |
                                  SUB                  SUB           



the simple answer is to build a bridge. a bridge is a small application that speaks one protocol at one socket, and converts to/from a second protocol at another socket. a protocol interpreter, if you like. a common bridging problem in zmq is to bridge two transports or networks

as an example, we're going to write a little proxy that sits in between a publisher and a set of subscribers, bridging two networks. the frontend socket (SUB) faces the internal network where the weather server is sitting, and the backend(PUB) faces subscribers on the external network. it subscribes to the weather service on the frontend socket, and republishes its data ont he backend socket. (`proxy.php`)

```php
<?php

/**
 * proxy.php
 * weather proxy device
 */

$context = new ZMQContext();

// this is where the weather server sits
$frontend = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$frontend->connect("tcp://192.168.55.210:5556");

// this is our public endpoint for subscirbers
$backend = new ZMQSocket($context, ZMQ::SOCKET_PUB);
$backend->bind("tcp://10.1.1.0:8100");

// subscribe on everything;
$frontend->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "");

// or may be can use build-in proxy function ZMQDevice()?
// // start built-in device
// $device = new ZMQDevice($frontend, $backend);
// $device->run();

// shunt message out to our own subscirbers
while (true) {
    while(true){
        // process all parts of the message
        $msg = $frontend->recv();
        $more = $frontend->getSockOpt(ZMQ::SOCKOPT_RCVMORE);
        $backend->send($msg, $more ? ZMQ::MODE_SNDMORE : 0);
        if(!$more){
            break; // last msg part
        }
    }
}
```

it looks very similar to the earlier proxy example, but the key part is that the frontend and backend sockets are on two different newwroks. we can use this model for example to connect a multicast network (pgm transoprt) or a tcp publiser


### Handling Errors and ETERM

zmq error handling philosophy is a mix of fail-fast and resilience. Processes, we believe, should be as vulnerable as po9ssible to internal errors, and as robust as possible against external attacks and errors. to give an analogy, a living cell will self-destruct if it detects a single internal error, yet it will resist attack from the ourside by all means possible.

assertions, which pepper the zmq code, are absolutely vital to robust code; they just have to be on th right side of the cellular wall. and there should be such a wall. if ti is unclear whether a fault is internal or external, that is a design flow to be fixed. in c/c++, assertions stop the application immediately with an error. in other languages, you may get exceptions or halts.

when zmq detects an external fault it returns an error to the calling code. in some rare cases, it drops messagers silently if there is no obvious strategy for recovering from the error.

in most of the C examples we've seen so far ther's been no error handling. Real Code Should do error handling on every single zmq call. if your're using a language being other than C, the binding may handle errors for you. in  C, you do need to do this y ourself. there are some simple rules, starting with POSIX conventions:
- methods that create objects return NULL if they fail
- methods that process data may return the number of bytes processed, or -1 on an error or failure
- other method return 0 on success and -1 on an error or failure
- the error code is provided in errno or `zmq_errno()`
- a descriptive error text for logging is provided by `zmq_strerror()`

for example 
```c
void *context = zmq_ctx_new();
assert(context);
void $socket = zmq_socket(context, ZMQ_REP);
assert (socket)
int rc = zmq_bind(socket, "tcp://*:5555");
if(rc == -1) {
    printf("E: bind failed: %s\n", strerror(errno));
    return -1;
}

```
there are two main exceptional conditions that you should handle as nonfatal:
- when your code receives a message with the `ZMQ_DONTWAIT` option and there is no waiting data, zmq will return -1 and set errno to `EAGAIN`
- when one thread calls `zmq_ctx_destory()`, and other threads are still doing blocking work, the `zmq_ctx_destory()` call closes the context and all locking calls exit with -1 and errno set to `ETERM`

in C/C++ asserts can be removed entirely in optimized code, so don't make the mistake of wrapping t he whole zmq call in an assert(). it looks neat; then the optimizer removes all the asserts and the calls you want to make, and your application breaks in impressive ways.


> Parallel pipeline with kill signaling

![kill](https://zguide.zeromq.org/images/fig19.png)

let's see how to shut down a process cleanly. we'll take the parallel pipeline example from the previous section. if we've started a whole lot of workers in the backgroud, we now want to kill them when the batch is finished. let's do this by sending a kill message to the workers. the best place to do this is the sink because it really knows when the batch is done.

how do we connect the sink to the workers? the PUSH/PULL sockets are one-way only. we could swtich to anotehr socket type, r we could mix multiple socket flows. lets try tht elatter: using a pub-sub model to send kill messages to the works:
- the sink creats a PUB socket on a new endpoint.
- workers connect their input socket to this endpoint
- when the sink detects the end of the batch, it send s a kill to it's pub socket.
- when a worker detects this kill message it exits.

it doesn't take much new code in the sink
```c
void *controller = zmq_socket(context, ZMQ_PUB);
zmq_bind(controller, "tcp://*.5559);
...
//send kill signal to workers
s_send(controller, "KILL");
```

here is the worker process, which manges two sockets(a PULL socket getting task, and a SUB socket getting control commands), using the `zmq_poll()` technique we saw earlier: (taskWork2.php taskSink2.php) 
> still can not receive KILL msg

## Handling interrupt signals
realistic applications need to shu down cleanly when interrupted with Ctrl-C or another singal such as SIGTERM. by default, these simply kill the process, meaning messages won't be flushed, files won't be closed cleanly and so on.

here is how we hanlde a signal in various languages  `interrupt.php`

the program provides s_catch_signals(), which traps Ctrl-C (`SIGINT`) and `SIGTERM` when either of these signals arrive, the `signalHandler()` handler sets the global variable `$running` to `false`. thanks to your signal handler, you application will not die automatically. instead, you have a chance to clean up and exit gracefully. you have to now explicitly check for an interrupt and handle it properly. do this by calling `signalHandler()` at the start of your main code. this sets up the signal handling. the interrupt will affect zmq calls as follows:
- if your code blocking in a blocking call(sending a message, receiving a message, or polling), then when a signal arrives, the call will return with `EINTR`
- wrappers like `s_recv()` return `NULL` if they are interrupted

so check for an `EINTR` return code, a `NULL` return and/or `s_interrupted`

if you call `signalHandler()` and on't test for interrupts, then your application will become immue to Ctrl-C and `SIGTERM` which may be useful but is usually not.
     
## Detecting Memory Leaks
https://zguide.zeromq.org/docs/chapter2/