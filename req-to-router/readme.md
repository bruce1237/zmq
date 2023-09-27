# how REQ to Router works
when ROUTER receives a request from REQ, it will add the following to the msg:
1. REQ-Identity: an string to find the REQ address
2. delimiter: empty frame


so when ROUTER reply the msg to the REQ, we can using the address/REQ-Identity to make sure we reply to the correct REQ server.
also can use `$req->setSockOpt(ZMQ::SOCKOPT_IDENTITY, WhatEverTheIdentityNameYouWishToHave);`  to define the identity value.