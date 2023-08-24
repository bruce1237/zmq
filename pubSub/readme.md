## for using PubSub module/pattern
- simply assume that the published data stream is infinite and has no start and no end. 
- One also assumes that the subscriber doesn’t care what transpired before it started up. 
    This is how we built our weather client example.
- A subscribe can connect to more than one publisher, using one connect call each time. data will then arrive and be interleaved(fair-queued) so that no single publisher drowns out the others.
- if a publisher has no connected subscribers, then it will simply drop all messages
- if you;re using TCP and a subscirbe is slow, messages will queue up on the publisher. we'll look at how to protect publishers against this using the `hight-water mark` 
- from ZMQ v3.x, filtering happens at the publisher side when using a connected protocol(tcp:@<>@ or ipc:@<>@), using the epgm(@<//>@ filtering heppens at the subscirber side.) in v2.x, all filtering happened at the subscriber side
- 

