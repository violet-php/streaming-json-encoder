<?php

use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/src');

return new Sami($iterator, [
    'title'                => 'Streaming JSON Encoder',
    'build_dir'            => __DIR__ . '/build/doc',
    'cache_dir'            => __DIR__ . '/build/cache',
    'remote_repository'    => new GitHubRemoteRepository('violet-php/streaming-json-encoder', __DIR__),
    'default_opened_level' => 2,
]);
