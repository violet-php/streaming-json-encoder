<?php

require_once __DIR__ . '/../vendor/autoload.php';

$encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder(['array_value']);
$encoder->encode();
