<?php

declare(strict_types=1);

require \dirname(__DIR__, 2).'/vendor/autoload.php';
$toolLoader = require __DIR__.'/vendor/autoload.php';
$toolLoader->unregister();
$toolLoader->register();

require_once __DIR__.'/ResolveViewReturnTypeExtension.php';
