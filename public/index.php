<?php

declare(strict_types=1);

use App\HistoryStore;
use App\MarkdownRenderer;
use App\NoteStore;
use App\Router;
use App\SessionService;
use App\UploadStore;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/Kernel.php';

$uri = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . $uri;
    if ($uri !== '/' && is_file($file)) {
        return false;
    }
    $base = '';
    $path = $uri;
} else {
    $script = str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']);
    $base = rtrim(dirname($script), '/');
    if ($base === '.' || $base === '\\') {
        $base = '';
    }
    $path = $uri;
    if ($base !== '' && str_starts_with($uri, $base)) {
        $path = substr($uri, strlen($base)) ?: '/';
    }
}

$path = '/' . ltrim($path, '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

define('BASE_PATH', $base);

SessionService::start();

$data = $root . '/data';
$kernel = new Kernel(
    new Router(),
    new NoteStore($data),
    new HistoryStore($data),
    new UploadStore($data),
    new MarkdownRenderer(),
    BASE_PATH,
    $root . '/app/views',
    $root . '/public',
);
$kernel->run($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
