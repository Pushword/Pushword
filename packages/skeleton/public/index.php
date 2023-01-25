<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;


require __DIR__.'/../../../vendor/autoload.php';

(new Dotenv())->loadEnv(__DIR__.'/../.env');

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'dev';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] ='1';


umask(0000);

if (class_exists(Debug::class)) {
    Debug::enable();
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
