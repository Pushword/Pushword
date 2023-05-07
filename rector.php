<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\FuncCall\IntvalToTypeCastRector;
use Rector\CodeQuality\Rector\FuncCall\StrvalToTypeCastRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\ClassConst\VarConstantCommentRector;
use Rector\Config\RectorConfig;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\PHPUnit\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();

    $paths = array_map(
        function ($path) { return __DIR__.'/'.$path; },
        array_values(\Safe\json_decode(\Safe\file_get_contents('composer.json'), true)['autoload']['psr-4']) // @phpstan-ignore-line
    );
    $paths[] = __DIR__.'/packages/skeleton/src';
    $rectorConfig->paths($paths);

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
        /*
        SetList::NAMING,
        SetList::PRIVATIZATION,
        */
    ]);

    $rectorConfig->skip([
        'packages/core/src/Twig/AppExtension.php',
        'packages/skeleton/src/Kernel.php',
        'packages/core/src/Component/App/AppConfig.php',
        AddSeeTestAnnotationRector::class,
        JsonThrowOnErrorRector::class,
        VarConstantCommentRector::class,
        StrvalToTypeCastRector::class,
        IntvalToTypeCastRector::class,
        CallableThisArrayToAnonymousFunctionRector::class,
        NullToStrictStringFuncCallArgRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
    ]);
};
