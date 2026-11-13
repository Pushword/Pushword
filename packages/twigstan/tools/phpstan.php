<?php

declare(strict_types=1);

putenv('PAO_DISABLE=1');
$_SERVER['argv'][] = '--autoload-file='.__DIR__.'/bootstrap.php';
require __DIR__.'/vendor/phpstan/phpstan/phpstan';
