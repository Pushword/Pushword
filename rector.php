<?php

declare(strict_types=1);
use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\FuncCall\IntvalToTypeCastRector;
use Rector\CodeQuality\Rector\FuncCall\StrvalToTypeCastRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Symfony42\Rector\MethodCall\ContainerGetToConstructorInjectionRector;

use function Safe\file_get_contents;
use function Safe\json_decode;

$paths = array_map(
    static fn ($path): string => __DIR__.'/'.$path,
    array_values(json_decode(file_get_contents('composer.json'), true)['autoload']['psr-4']) // @phpstan-ignore-line
);
$paths[] = __DIR__.'/packages/skeleton/src';

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
        SymfonySetList::SYMFONY_63,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withSkip([
        'packages/core/src/Twig/AppExtension.php',
        'packages/skeleton/src/Kernel.php',
        'packages/core/src/Component/App/AppConfig.php',
        AddSeeTestAnnotationRector::class,
        JsonThrowOnErrorRector::class,
        StrvalToTypeCastRector::class,
        IntvalToTypeCastRector::class,
        CallableThisArrayToAnonymousFunctionRector::class,
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        ContainerGetToConstructorInjectionRector::class, // for AbstractGenerator and PageScannerService
    ])
;
