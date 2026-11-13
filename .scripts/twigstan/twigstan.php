<?php

declare(strict_types=1);

$root = \dirname(__DIR__, 2);
$config = require __DIR__.'/config.php';

return $config
    ->twigPaths(...array_merge(glob($root.'/packages/*/src/templates', \GLOB_ONLYDIR), [$root.'/packages/dev-app/templates', $root.'/packages/docs/templates']))
    ->create();
