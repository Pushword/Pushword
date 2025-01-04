<?php

declare(strict_types=1);
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

$paths = [
    __DIR__.'/packages',
    __DIR__.'/monorepo-builder.php',
    __DIR__.'/.php-cs-fixer.dist.php',
    __DIR__.'/rector.php',
    __DIR__.'/.scripts/generate-docs-assets',
];

return RectorConfig::configure()
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets(php83: true)
    ->withParallel()
    ->withPaths($paths)
    ->withRootFiles()
    ->withSymfonyContainerPhp(__DIR__.'/packages/skeleton/tests/symfonyContainer.php')
    ->withComposerBased(
        doctrine: true,
        twig: true,
        phpunit: true
    )
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        earlyReturn: true,
        typeDeclarations: true,
        instanceOf: true,
        symfonyCodeQuality: true,
        // symfonyConfigs: true
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true
    )
    ->withSkip([
        'packages/core/src/Twig/AppExtension.php',
        'packages/skeleton/src/Kernel.php',
        'packages/core/src/Component/App/AppConfig.php',
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
;
