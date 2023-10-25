<?php
    $context = new ZMQContext();
    $worker = $context->getSocket(ZMQ::SOCKET_REQ);
    $worker->connect("tcp://localhost:5556");

    //  Tell broker we're ready for work
    $worker->send("READY");
    echo "W: send READY\n";


    while (true) {
        //  Read and save all frames until we get an empty frame
        //  In this example there is only 1 but it could be more
        $address = $worker->recv();
        echo "W: receive Address: {$address}\n";


        // Additional logic to clean up workers.
        if ($address == "END") {
            exit();
        }
        $empty = $worker->recv();
        echo "W: received delimiter\n";


        //  Get request, send reply
        $request = $worker->recv();
        echo "W: received request: {$request}\n";


        $worker->send($address, ZMQ::MODE_SNDMORE);
        $worker->send("", ZMQ::MODE_SNDMORE);
        $worker->send("OK");

        echo "W: send OK as response\n";

    }