<?php

declare(strict_types=1);

putenv('PAO_DISABLE=1');
array_splice($_SERVER['argv'], 1, 0, ['--autoload-file='.__DIR__.'/bootstrap.php']);
require __DIR__.'/vendor/phpstan/phpstan/phpstan';
