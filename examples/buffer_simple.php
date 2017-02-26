<?php

require '../vendor/autoload.php';

$encoder = new \Violet\StreamingJsonEncoder\BufferJsonEncoder(['array_value']);
echo $encoder->encode();
