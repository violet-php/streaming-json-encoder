<?php

require '../vendor/autoload.php';

$fp = fopen('test.json', 'w');
$encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder(
    range(1, 100),
    function ($json) use ($fp) {
        fwrite($fp, $json);
    }
);

$encoder->encode();
fclose($fp);
