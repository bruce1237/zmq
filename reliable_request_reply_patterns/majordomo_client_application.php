<?php
/**
 * majordomo protocol client example
 * uses the mdcli api to hide all DMP aspects
 */

 include_once "../mdcliapi.php";

 $verbose = $_SERVER['argc']>1 && $_SERVER['argv'][1]=='-v';
 $session = new MDCli("tcp://localhost:5555", $verbose);
 for ($count = 0; $count < 100000; $count++){
    $request = new Zmsg();
    $request->body_set("hello world");
    $reply = $session->send("echo", $request);
    if(!$reply ){
        break; //interrupt or failure
    }
 }
 printf("%d requests/replies processd", $count);
echo PHP_EOL;
