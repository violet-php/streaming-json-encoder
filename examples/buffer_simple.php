<?php

require_once __DIR__ . '/../vendor/autoload.php';

$encoder = new \Violet\StreamingJsonEncoder\BufferJsonEncoder(['array_value']);
echo $encoder->encode();
