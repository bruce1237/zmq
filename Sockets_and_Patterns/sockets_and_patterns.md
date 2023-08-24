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

https://zguide.zeromq.org/docs/chapter2/