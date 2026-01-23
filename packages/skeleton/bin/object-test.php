<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$autoload = __DIR__.'/../vendor/autoload.php';
$autoload = file_exists($autoload) ? $autoload : __DIR__.'/../../../vendor/autoload.php';
require $autoload;

new Dotenv()->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
