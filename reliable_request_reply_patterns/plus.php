<?php

function test(int $a){
    echo $a, PHP_EOL;
}

$a=0;
$b=0;
test(++$a);
echo 'AAA',$a, PHP_EOL;
test($b++);
echo 'BBB', $b, PHP_EOL;