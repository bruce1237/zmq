# this is zmq test
`to install git install php-zmq`
- is a messaging library
- has 5 messaging patterns
- brokerless

## messaging patterns
- Synchronous Request/Response
- Asynchronous Request/Response
- Publish/Subscribe
- Push/pull
- Exclusive Pair

## socket Types
- REQ (socket only send request, you create a socket then connect to it. like client)
- REP (socket only send reply, you create then connect, like server)
- 
- PUSH (like server, you push msg to it)
- PULL (like client, you pull msg from it)
- 
- DEALER (is a client)
- ROUTER (is a server): spin up a router server, then connect REQ to it. router will assign identity to these connections

>for Pub/Sub, publisher will publish a job to all subs, 
>but in Push/Pull, puller will only pull one job and that pulled job will be removed from server
