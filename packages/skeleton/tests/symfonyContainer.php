<?php

// Used by rector
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

$autoload = __DIR__.'/../vendor/autoload.php';
$autoload = file_exists($autoload) ? $autoload : __DIR__.'/../../../vendor/autoload.php';
require $autoload;

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);

/** @var Kernel $kernel */
return $kernel->getContainer();
