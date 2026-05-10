<?php

use App\Kernel;

$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'prod';
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'prod';
putenv('APP_ENV=prod');

$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '0';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '0';
putenv('APP_DEBUG=0');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel('prod', false);
};