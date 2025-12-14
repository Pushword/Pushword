<?php

use PhpCsFixer\Config;
use Symfony\Component\Finder\Finder;

$finder = Finder::create()
    ->in([
        __DIR__.'/packages',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->append([
        __DIR__.'/monorepo-builder.php',
        __DIR__.'/.php-cs-fixer.dist.php',
        __DIR__.'/rector.php',
        __DIR__.'/.scripts/generate-docs-assets',
    ]);

$rules = require __DIR__.'/packages/skeleton/php-cs-fixer-rules.php';

return new Config()
    ->setRules($rules)
    ->setRiskyAllowed(true)
    ->setFinder($finder);
