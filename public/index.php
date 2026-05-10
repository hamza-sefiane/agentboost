<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel(
        $_SERVER['APP_ENV'] ?? 'prod',
        (bool) ($_SERVER['APP_DEBUG'] ?? false)
    );
};