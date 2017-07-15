<?php

require_once __DIR__ . '/../vendor/autoload.php';

$fp = fopen('test.json', 'wb');
$encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder(
    range(1, 100),
    function ($json) use ($fp) {
        fwrite($fp, $json);
    }
);

$encoder->encode();
fclose($fp);
