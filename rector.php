<?php

declare(strict_types=1);
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
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
    ->withParallel()
    ->withPaths($paths)
    ->withRootFiles()
    ->withSymfonyContainerPhp(__DIR__.'/packages/skeleton/tests/symfonyContainer.php')
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, codingStyle: true, earlyReturn: true, typeDeclarations: true, instanceOf: true)
    ->withSets([
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        SymfonySetList::SYMFONY_64,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withRules([
        PreferPHPUnitSelfCallRector::class,
    ])
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withSkip([
        'packages/core/src/Twig/AppExtension.php',
        'packages/skeleton/src/Kernel.php',
        'packages/core/src/Component/App/AppConfig.php',
        AddSeeTestAnnotationRector::class,
        JsonThrowOnErrorRector::class,
        // StrvalToTypeCastRector::class,
        // IntvalToTypeCastRector::class,
        PreferPHPUnitThisCallRector::class,
        // OrderByKeyToClassConstRector::class, // inject deprecate Criteria::DESC instead of Order::Descending
        // CallableThisArrayToAnonymousFunctionRector::class,
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
;
