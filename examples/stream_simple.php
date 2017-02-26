<?php

require '../vendor/autoload.php';

$encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder(['array_value']);
$encoder->encode();
