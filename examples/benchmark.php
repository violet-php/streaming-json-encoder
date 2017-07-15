<?php

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('memory_limit', '2048M');

$payload = [];
$longString = str_repeat('Lorem ipsum ', 1000);
$node = [
    'boolean' => true,
    'integer' => 1879234,
    'float' => 1.234900,
    'string' => 'stringy thingy',
    'array' => [
        'value 1',
        'value 2',
        'value 3',
    ],
    'very_long' => $longString,
];

for ($i = 0; $i < 10000; $i++) {
    $payload[] = $node;
}

function benchmark(Closure $callback)
{
    $timer = microtime(true);

    ob_start(function () {
        return '';
    }, 1024 * 8);

    $bytes = $callback();

    ob_end_flush();
    printf(
        'Output: %s kb, %d ms, Mem %d mb',
        number_format($bytes / 1024),
        (microtime(true) - $timer) * 1000,
        memory_get_peak_usage(true) / 1024 / 1024
    );
}

echo 'Streaming: ';
benchmark(function () use ($payload) {
    $encoder = new \Violet\StreamingJsonEncoder\StreamJsonEncoder($payload);
    $encoder->setOptions(JSON_PRETTY_PRINT);
    return $encoder->encode();
});

echo "\nDirect:    ";
benchmark(function () use ($payload) {
    $output = json_encode($payload, JSON_PRETTY_PRINT);
    echo $output;
    return strlen($output);
});
