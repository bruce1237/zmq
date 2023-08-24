# Fair queuing

push (R1, R2, R3) ----
                      |
push (R4) --------------->fair Queuing(R1, R4, R5, R2, R6, R3)--->PULL
                      |
push (R5, R6) --------


the pipeline pattern also exhibits the `slow Joiner` syndrome, leading to accusations that PUSH sockets don't load balance properly. if you are using PUSH and PULL, and one of your workers gets way more messages than the others. it's because the PULL socket has joined faster then the others, and grabs a lot of messages before the others manage to connect. if you want proper load balancing, you probably want to look at the load balancing pattern in `advanced request-reply patterns.`