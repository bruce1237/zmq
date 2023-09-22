<?php
$context = new ZMQContext();

echo "collecting updates from weather server ...", PHP_EOL;

$subscriber = new ZMQSocket($context, ZMQ::SOCKET_SUB);
$subscriber->bind("tcp://*:5556");

//subscribe to zipcode, default is NYC, 10001
$filter = $_SERVER['argc'] > 1 ? $_SERVER['argv'][1] : "10001";
$filter = '';

// setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE...) is define this is a subscriber
$subscriber->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, $filter);




$total_temp = 0;

for ($update_nbr = 0; $update_nbr < 10; $update_nbr ++) {
    $string =$subscriber->recv();
    echo "RRRRRR:".$string.PHP_EOL;
     sscanf($string, "%d %d %d", $zipCode, $temperautre, $relhumidity);
     $total_temp += $temperautre;
}

printf("average temperature for zipcode '%s' was %dF\n", 
    $filter, (int)($total_temp / $update_nbr));