<?php

require_once __DIR__ . '/../vendor/autoload.php';

$encoder = new \Violet\StreamingJsonEncoder\BufferJsonEncoder(function () {
    for ($i = 0; $i <= 10; $i++) {
        yield $i;
    }
});

foreach ($encoder as $string) {
    echo $string;
}
