<?php

require_once __DIR__ . '/../vendor/autoload.php';

$encoder = new \Violet\StreamingJsonEncoder\BufferJsonEncoder(range(0, 10));

foreach ($encoder as $string) {
    echo $string;
}
