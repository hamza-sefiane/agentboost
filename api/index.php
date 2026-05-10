<?php

use App\Kernel;

$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'prod';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '0';

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel(
        $_SERVER['APP_ENV'],
        (bool) $_SERVER['APP_DEBUG']
    );
};