<?php

declare(strict_types=1);
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

$paths = [
    __DIR__.'/src',
    __DIR__.'/.php-cs-fixer.dist.php',
    __DIR__.'/rector.php',
];

return RectorConfig::configure()
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets(php83: true)
    ->withParallel()
    ->withPaths($paths)
    ->withRootFiles()
    ->withSymfonyContainerPhp(__DIR__.'/tests/symfonyContainer.php')
    ->withComposerBased(
        twig: true,
        doctrine: true,
        phpunit: true
    )
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        instanceOf: true,
        earlyReturn: true,
        symfonyCodeQuality: true,
        // symfonyConfigs: true
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
        phpunit: true,
    )
    ->withSkip([
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
;
