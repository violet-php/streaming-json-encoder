<?php

require_once __DIR__ . '/../vendor/autoload.php';

$iterator = function () {
    foreach (new DirectoryIterator(__DIR__) as $file) {
        yield $file->getFilename();
    }
};

$encoder = (new \Violet\StreamingJsonEncoder\BufferJsonEncoder($iterator))
    ->setOptions(JSON_PRETTY_PRINT);

$stream = new \Violet\StreamingJsonEncoder\JsonStream($encoder);

while (!$stream->eof()) {
    echo $stream->read(1024 * 8);
}
