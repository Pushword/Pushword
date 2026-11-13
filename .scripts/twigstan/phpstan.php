<?php

declare(strict_types=1);

$_SERVER['argv'][] = '--autoload-file='.__DIR__.'/autoload.php';
require __DIR__.'/vendor/phpstan/phpstan/phpstan';
