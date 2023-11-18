<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\FuncCall\IntvalToTypeCastRector;
use Rector\CodeQuality\Rector\FuncCall\StrvalToTypeCastRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Bridge\Symfony\Routing\SymfonyRoutesProvider;
use Rector\Symfony\Configs\Rector\ClassMethod\AddRouteAnnotationRector;
use Rector\Symfony\Contract\Bridge\Symfony\Routing\SymfonyRoutesProviderInterface;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Symfony42\Rector\MethodCall\ContainerGetToConstructorInjectionRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();

    $paths = array_map(
        function ($path) { return __DIR__.'/'.$path; },
        array_values(\Safe\json_decode(\Safe\file_get_contents('composer.json'), true)['autoload']['psr-4']) // @phpstan-ignore-line
    );
    $paths[] = __DIR__.'/packages/skeleton/src';
    $rectorConfig->paths($paths);
    $rectorConfig->symfonyContainerPhp(__DIR__.'/packages/skeleton/tests/symfonyContainer.php');
    $rectorConfig->singleton(SymfonyRoutesProvider::class);
    $rectorConfig->alias(SymfonyRoutesProvider::class, SymfonyRoutesProviderInterface::class);

    // $parameters->rule(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_80);
    // $containerConfigurator->import(SetList::PHP_80);
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        LevelSetList::UP_TO_PHP_80,
        LevelSetList::UP_TO_PHP_81,
        SetList::CODING_STYLE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        // SetList::DEAD_CODE,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        // SetList::NAMING,
        SetList::CODE_QUALITY,
        SetList::PHP_82,
        SetList::CODING_STYLE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::INSTANCEOF,
        // SetList::PRIVATIZATION,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        SymfonySetList::SYMFONY_63,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        // SymfonySetList::CONFIGS,
    ]);

    // $rectorConfig->rule(AddRouteAnnotationRector::class);

    $rectorConfig->skip([
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
    ]);
};
