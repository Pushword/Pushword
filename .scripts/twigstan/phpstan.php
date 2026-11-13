<?php

declare(strict_types=1);

foreach ([
    'PUSHWORD_TWIGSTAN_PROJECT_DIR' => \dirname(__DIR__, 2),
    'PUSHWORD_TWIGSTAN_CONFIGURATION' => __DIR__.'/twigstan.php',
] as $name => $value) {
    $_ENV[$name] = $_SERVER[$name] = $value;
    putenv($name.'='.$value);
}

require \dirname(__DIR__, 2).'/packages/twigstan/tools/phpstan.php';
