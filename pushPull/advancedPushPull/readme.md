# ventilator -> worker -> sink model

                    --> worker(pull) push --
                    |                       |
ventilator(push)-----> worker(pull) push ----> sink(pull)
                    |                       |
                    --> worker(pull) push --

                    
- ventilator that produces tasks that can be done in parallel
- a set of workers that process tasks
- a sink that collects results back from the worker processes

## how it works
- the workers connect upstream to the ventilator, and downstream to the sink. this means you can add workers arbitrarily. if the workers bound to their endpoints, you would need a) more endpoints or b)to modify the ventilator and/or the sink each time you added a worker.  we say that the ventilator and sink are stable parts of our architecture and the workers are dynamic parts of it
- we have to synchronize the start of the batch with all workers being up and running. this is a fairly common gotcha in ZMQ and there is no easy solution. the zmq_connect method takes a certain time. so when a set of workers connect to the ventilator, the first one to successfully connect will get a whole load of messages in the short time while the others are also connecting. if you don't synchronize the start of the batch somehow, the system won't run in parallel at all. try removing the wait in the ventilator, and see what happens
- the ventilator's PUSH socket distributes tasks to workers(assuming they are all connected before the batch starts going out) evenly. this is called `load balancing` and it's something we'll look at agin in more details.
- the sink's PULL socket collects results from workers evenly. this is called `fair-queuing`