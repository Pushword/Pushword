<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\FuncCall\AssertFuncCallToPHPUnitAssertRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\StringCastAssertStringContainsStringRector;
use Rector\Privatization\Rector\ClassConst\PrivatizeFinalClassConstantRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Rector\Symfony\CodeQuality\Rector\Class_\ControllerMethodInjectionToConstructorRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

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
    ->withSymfonyContainerPhp(__DIR__.'/packages/dev-app/tests/symfonyContainer.php')
    ->withComposerBased(
        twig: true,
        doctrine: true,
        phpunit: true,
        symfony: true,
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        // symfonyConfigs: true (disabled: rewrites break service definitions, see services.php)
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true
    )
    ->withSets([
        // Symfony version sets are composer-based since rector 2.6.2
        // (SymfonySetList::SYMFONY_* constants removed): withComposerBased above.
        DoctrineSetList::TYPED_COLLECTIONS_DOCBLOCKS,
    ])
    ->withRules([
        DeclareStrictTypesRector::class,
        PrivatizeFinalClassPropertyRector::class,
        PrivatizeFinalClassMethodRector::class,
        PrivatizeFinalClassConstantRector::class,
    ])
    ->withSkip([
        __DIR__.'/packages/*/rust/target/*',
        __DIR__.'/packages/core/rust/benchmarks/local-altimood/*',
        'packages/core/src/Twig/AppExtension.php',
        'packages/dev-app/src/Kernel.php',
        'packages/core/src/Site/SiteConfig.php',
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        PreferPHPUnitThisCallRector::class,
        AssertFuncCallToPHPUnitAssertRector::class,
        StringCastAssertStringContainsStringRector::class,
        // @see https://github.com/rectorphp/rector/issues/9519
        ControllerMethodInjectionToConstructorRector::class => [
            '*CrudController.php',
            'DashboardController.php',
        ],
        // `setFileCache()` reads the property to decide whether to overwrite it,
        // so it is read before it is ever assigned. Dropping the default makes
        // the first call a fatal — and it runs from the page-cache refresh
        // handler, so every test that persists a Page goes down with it.
        RemoveDefaultValueFromAssignedPropertyRector::class => [
            'packages/page-scanner/src/Controller/PageScannerController.php',
        ],
    ])
;
