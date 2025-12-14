<?php

declare(strict_types=1);
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Symfony\CodeQuality\Rector\Class_\ControllerMethodInjectionToConstructorRector;
use Rector\Symfony\Set\SymfonySetList;

$paths = [
    __DIR__.'/packages',
    __DIR__.'/monorepo-builder.php',
    __DIR__.'/.php-cs-fixer.dist.php',
    __DIR__.'/rector.php',
    __DIR__.'/.scripts/generate-docs-assets',
];

return RectorConfig::configure()
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets(php84: true)
    ->withParallel()
    ->withPaths($paths)
    ->withRootFiles()
    ->withSymfonyContainerPhp(__DIR__.'/packages/skeleton/tests/symfonyContainer.php')
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
        doctrine: true
    )
    ->withSets([
        SymfonySetList::SYMFONY_80,
    ])
    ->withSkip([
        'packages/core/src/Twig/AppExtension.php',
        'packages/skeleton/src/Kernel.php',
        'packages/core/src/Component/App/AppConfig.php',
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        // @see https://github.com/rectorphp/rector/issues/9519
        ControllerMethodInjectionToConstructorRector::class => [
            '*CrudController.php',
            'DashboardController.php',
        ],
    ])
;
