<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../../../vendor/autoload.php';

new Dotenv()->loadEnv(__DIR__.'/../.env');

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ??= 'dev';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] ??= '1';

umask(0000);

if (class_exists(Debug::class)) {
    Debug::enable();
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
restore_error_handler();
restore_exception_handler();
$kernel->terminate($request, $response);
