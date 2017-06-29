<?php

use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__ . '/src');

$theme = getenv('SAMI_THEME');
$settings = [];

if ($theme) {
    $settings['theme'] = basename($theme);
    $settings['template_dirs'] = [dirname($theme)];
}

return new Sami($iterator, $settings + [
    'title'                => 'Streaming JSON Encoder API',
    'build_dir'            => __DIR__ . '/build/doc',
    'cache_dir'            => __DIR__ . '/build/cache',
    'remote_repository'    => new GitHubRemoteRepository('violet-php/streaming-json-encoder', __DIR__),
    'default_opened_level' => 2,
]);
