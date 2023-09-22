# chapter 3 advanced request-reply patterns
- how the request-reply mechanisms work
- how to combine REQ, REP, DEALER and ROUTER sockets
- how ROUTER sockets work, in detail
- the load balancing pattern
- building a simple load balancing message broker
- designing a high-level API for zmq
- building an asynchronous request-reply server
- a detailed inter-broker routing example

## the request-reply mechanisms
we already looked briefly at multipart messages. let's now look at a major use case, which is `reply message envelopes`. An envelop is a way of safely packaging up data with an address, without touching the data itself. by separating reply addresses into an envelop we make it possible to write general purpose intermediaries such as APIs and proxies that create, read and remove addresses no matter what the message payload or structure is.

in the request-reply pattern, the envelop holds the return address for replies. it is how a zmq network with no state can create round-trip request-reply dialogs.

when you use REQ and REP sockets you don't even see envelopes; these sockets deal with them automatically. but for most of the interesting request-reply patterns, you'll want to understand envelops and particularly ROUTER sockets. 

### the simple reply ENVELOPE
a request-reply exchange consists of a request message, and an eventual reply message. in the simple request-reply pattern, there's one reply for each request. in more advanced patterns, requests and replies can flow asynchronously. however, the reply envelope always works the same way.

the zmq reply envelop formally consists of zero or more reply addresses, followed by an empty frame(the envelop delimiter), followed by the message body(zero or more frames). the envelop is created by multiple sockets working together in a chain. we'll break this down.

we'll start by sending "hello" through a REQ socket. the REQ socket creates the simplest possible reply envelop, which has no addresses, just an empty delimiter frame and the message frame containing the "hello" string. this is a two frame message

![request with minimal envelop](image.png)

the REP socket does the matching work: it strips off the envelop, up to and including the delimiter frame, saves the whole envelope, and passes the "hello" string up the application. thus our original hellow world example used request-reply envelopes internally, bu the application never saw them.

if you spy on the network data flowing between `hwclient` and `hwserver`, this is what you'll see: every request and every reply is in fact two frames, an empty frame and then the body. it doesn't seem to make much sense for a simple REQ-REP dialog. however you'll see the reason when we explore how ROUTER and DEALER handle envelopes;

### the extended reply envelope
let's extend the REQ-REP pair with a ROUTER-DEALER proxy in the middle and see how this affects the reply envelop. this is the extended request-reply pattern, we can in fact, insert any number of proxy steps the mechanics are the same

![Extended request-reply pattern](image-1.png)

the proxy does this in pseudo-code:
```code
prepare context, frontend and backend sockets
while true:
    poll on both sockets
    if frontend had input:
        read all frames from frontend
        send to backend
    if backend had input:
        read all frames from backend
        send to frontend
```

the ROUTER socket, unlike other sockets, tracks every connection it has, and tells the caller about these. the way it tells the caller is to stick the connection identity in front of each message received. an identity, sometimes called an address, is just a binary string with no meaning except "this is a unique handle to the connection". then when you send a message via a ROUTER socket, you first send an identity frame.

the `zmq_socket()` man page describe it thus:
>when receiving messages a ZMQ_ROUTER socket shall prepend a message part containing the identity of the originating peer to the message before passing it to the application. messages received are fair-queued from among all connected peers. when sending messages a ZMQ_ROUTER socket shall remove the first part of the message and use it to determine the identity of the peer the message shall be routed to.

as a historical note, zmq v2.2 and earlier use UUIDs as identities. zmq v3.0+ generate a 5 byte identity by default(0+a random 32bit integer). there's some impact on network performance, but only when you use multiple proxy hops, which is rare. mostly the change was to simplify building libzmq by removing the dependency on a UUID lib.

Identities are a difficult concept to understand, but it's essential if you want to become a zmq expert. the ROUTER socket create a random identity for each connection with which it works. if there are three REQ sockets connected to a ROUTER socket, it will create tree random identities, one for each REQ socket.

so if we continue our worked example, let's say the REQ socket has a 3-byte identity ABC. internally, this means the ROUTER socket keeps a hash table where it can search for ABC to find the TCP connection for the REQ socket.

when we receive the message off the ROUTER socket, we get three frames

![Request with one Address](image-2.png)

the core of the proxy loop is "read from one socket, write to the other", so we literally send these three frames out on the DEALER socket. if you now sniffed the network traffic, you would see these three frames flying from the DEALER socket to the REP socket. the REP socket does as before, strips off the whole envelop including the new reply address, and once again delivers the "hello" to the caller.

incidentally the REP socket can only deal with one request-reply exchange at a time, which is why if you try to read multiple requests or send multiple replies without sticking to a strct recv-send cycle, it gives an error.

you should now be able to visualize the return path. when hwserver sends "world" back, the REP socket wraps that with the envelop it saved, and sends a three-frame reply message across the write to the DEALER socket.

![reply with one address](image-3.png)

now the DEALER reads these three frames, and sends all three out via the ROUTER socket. the Router takes the first frame for the message, which is ABC identity, and loos up the connection for this. if it finds that, it then pumps the next frames out onto the wire.

![reply with minimal envelop](image-4.png)

the REQ socket picks this message up, and checks that the first frame is the empty delimiter, which it is. the REQ socket discards that first frame and passes "world" to the calling application, which prints it out to the amazement of the younger us looking at zmq for the first time.

![how it works req-router-dealer-rep](429C344B-C628-4762-80CF-2E5EF6BA9E82_1_102_o.jpeg)

### what's this good for?
to be honest, the use cases for strict request-reply or extended request-reply are somewhat limited. for one thing, there's no easy way to recover from common failures like the server creashing due to buggy application code. <more in chapter 4>. however once you grasp the way these four sockets del with envelopes, and how they talk to each other, you can do very useful things. we saw how ROUTER uses the reply envelope to decide which client REQ socket to route a reply back to. now let's express this another way:
- each time ROUTER gives you a message, it tells you what peer that came from as an identity.
- you can use this with a hash table(with the identity as key) to track new peers as they arrive
- ROUTER will route messages asynchronously to any peer connected to it, if you prefix the identity as the first frame of the message.

ROUTER sockets don't care about the whole envelop. they don't know anything about the empty delimiter. all they care about is that one identity frame that lets them figure out which connection to send a message to.

### recap of request-reply sockets
- the REQ socket sends, to the network, an empty delimiter frame in front of the message data. REQ sockets are synchronous. REQ sockets always send one request and then wait for one reply. REQ sockets talk to one peer at a time. if you connect a REQ socket to multiple peers, requests are distributed to and replies expected from each peer one turn at a time.
- the REP socket reads and saves all identity frames up to and including the empty delimiter, then passes the following frame or frames to the caller. REP sockets are synchronous and talk to one peer at a time. if you connect a REP socket to multiple peers, requests are read from peers in fair fashion, and replies are always sent to the same peer that made the last request.
- the DEALER socket is oblivious to the reply envelope and handles this like any multipart message. DEALER sockets are asynchronous and like PUSH and PULL combined. they distribute sent messages among all connections, and fair-queue received messages from all connections.
- The ROUTER socket is oblivious to the reply envelop, like DEALER. it creates identities for its connections, and passes these identities to the caller as a first frame in any received message. conversely, when the caller sends a message, it uses the first message frame as an identity to loop up the connection to send to. ROUTER are asynchronous.


## Request-Reply Combinations
we have four request-reply sockets, each with a certain behavior. we've seen how they connect in simple and extended request-reply patterns. but these sockets are building blocks that you can use to solve many problems.

### the legal combinations
- REQ    -> REP
- DEALER -> REP
- REQ    -> ROUTER
- DEALER -> ROUTER
- DEALER -> DEALER
- ROUTER -> ROUTER

### invalid combinations
- REQ -> REQ
- REQ -> DEALER
- REP -> REP
- REP -> ROUTER

here are some tips for remembering the semantics. DEALER is like an asynchronous REQ socket, and ROUTER is like an asynchronous REP socket. where we use a REQ socket, we can use a DEALER; we just have to read and write the envelope ourselves. where we use a REP socket, we can stick a ROUTER; we just need to manage the identities ourselves.

think of REQ and DEALER sockets as "Clients" and REP and ROUTER sockets as "servers".  mostly, you'll want to bind REP and ROUTER sockets, and connect REQ and DEALER sockets to them.
**bind server, connect to client**
it's not always going to be this simple, but it is a clean and memorable place to start.

## REQ and REP combination
the REQ client MUST initiate the message flow. a REP server cannot talk to a REQ client that hasn't first sent it a request. technically, it's not even possible, and the API also returns an EFSM error if you try it.

## DEALER and REP combination
let's replace the REQ client with a DEALER. this gives us an asynchronous client that can talk to multiple REP servers. if we rewrote the "Hello world" client using DEALER, we'd be able to send off any number of "Hello" requests without waiting for replies.

when we use a DEALER to talk to a REP socket, we MUST accurately emulate the envelope that the REQ socket would have sent, or the REP socket will discard the message as invalid. so to send a message, we: 
1. send an empty message frame with the MORE flag
2. send the message body.

and when we receive a message, we:
1. receive the first frame and if it's not empty, discard the whole message
2. receive the next frame and pass that to the application.

## the REQ and ROUTER combination
in the same way that we can replace REQ with DEALER, we can replace REP with ROUTER. this gives us an asynchronous server that can talk to multiple REQ clients at the same time. if we rewrote the "hello world" service using Router, we'd be able to process any number of "hello" reuests in parallel. 

we can use Router in two distinct ways:
- as a proxy that switches messages between frontend and backend sockets
- as an application that reads the message and acts on it.

in the first case, the ROUTER simply reads all frames, including the artificial identity frame, and passes them on blindly. in the second case the ROUTER must know the format of the reply envelop it's being sent. as the other peer is a REQ socket, the ROUTER gets the identity frame, an empty frame, and then the data frame.

## the DEALER to ROUTER combination
now we can switch out bother REQ and REP with DEALER and ROUTER to get the most powerful socket combination, which is DEALER talking to ROUTER. it gives us asynchronous clients talking to asynchronous servers, where both side have full control over the message formats.

because both DEALER and ROUTER can work with arbitrary message formats, if you hope to use these safely, you have to become a little bit of a protocol designer. at the very least you must decide whether you with to emulate the REQ/REP reply envelop. it depends on whether you actually need to send replies or not.

## the ROUTER to ROUTER combination
this sounds perfect for N-to-N connections, but it's the most difficult combination to use. you should avoid it untill well advanced.

![connection summary](3C84A47A-9989-49E7-8CB9-F0F19A6F7FE9_1_102_o.jpeg)

## Invalid Combinations
### REQ to REQ
both side want to start by sending messages to each other, and this could only work if you timed thins so that both peers exchanged messages at the same time. it hurts my brain to even think about it.

### REQ to DEALER
you could in theory do this, but it would break if you added a second REQ because DELER has no way of sending a reply to the original peer. thus the REQ socket would get confused, and/or return messages meant for another client.

### REP to REP
both side would wait for the other to send the first message

### REP to ROUTER
the ROUTER socket can in theory initiate the dialog and send a properly-formatted request, if it knows the REP socket has connected and it knows the identity of that connection. it's messy and adds nothing over DEALER to ROUTER

the common thread in this valid versus invalid breakdown is that a zmq socket connection is always biased towards one peer that binds to an endpoint, and another that connects to that. further, that which side binds and which side connects is not arbitrary, but follows natural patterns. the side which we expected to be there binds: it'll be a server, a broker, a publisher, a collector. the side that comes and goes "connects": it'll be clients and workers. remembering this will help you design better zmq architectures

## Exploring ROUTER Sockets


https://zguide.zeromq.org/docs/chapter3/