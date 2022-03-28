<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Assign\CombinedAssignRector;
use Rector\CodeQuality\Rector\BooleanNot\SimplifyDeMorganBinaryRector;
use Rector\CodeQuality\Rector\Foreach_\SimplifyForeachToCoalescingRector;
use Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector;
use Rector\CodeQuality\Rector\Identical\SimplifyConditionsRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassConst\VarConstantCommentRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\DeadCode\Rector\Assign\RemoveAssignOfVoidReturnFunctionRector;
use Rector\DeadCode\Rector\BooleanAnd\RemoveAndTrueRector;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\For_\RemoveDeadIfForeachForRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\PropertyProperty\RemoveNullPropertyInitializationRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Identical\StrEndsWithRector;
use Rector\Php80\Rector\Identical\StrStartsWithRector;
use Rector\Php80\Rector\NotIdentical\StrContainsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Class_\ChangeLocalPropertyToVariableRector;
use Rector\Set\ValueObject\SetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\Strict\Rector\If_\BooleanInIfConditionRuleFixerRector;
use Rector\Strict\Rector\Ternary\BooleanInTernaryOperatorRuleFixerRector;
use Rector\Strict\Rector\Ternary\DisallowedShortTernaryRuleFixerRector;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddArrayParamDocTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddArrayReturnDocTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddMethodCallBasedStrictParamTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByParentCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedPropertyRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureReturnTypeRector;
use Rector\TypeDeclaration\Rector\FunctionLike\ParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\FunctionLike\ReturnTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\Property\PropertyTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\PHPStanTwigRules\Rules\NoTwigMissingVariableRule;

return static function (ContainerConfigurator $containerConfigurator): void {
    // get parameters
    $parameters = $containerConfigurator->parameters();

    $parameters->set(
        Option::PATHS,
        array_map(
            function ($path) { return __DIR__.'/'.$path; },
            array_values(json_decode(file_get_contents('composer.json'), true)['autoload']['psr-4'])
        )
    );

    $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_74);

    //$containerConfigurator->import(SetList::DEAD_CODE);
    //$containerConfigurator->import(SymfonySetList::SYMFONY_CODE_QUALITY);

    $tmp = [
        //SetList::ACTION_INJECTION_TO_CONSTRUCTOR_INJECTION, => Ok
        //SetList::CODE_QUALITY,
        //SetList::CODING_STYLE,
        //SetList::FRAMEWORK_EXTRA_BUNDLE_50,               => OK
        //SetList::NAMING,
        //SetList::PHP_74,                                  => OK
        //SetList::PSR_4,                                   => not applied for config files
        //SetList::SAFE_07,                                 => ok, cool
        //SetList::TYPE_DECLARATION,
        //SetList::TYPE_DECLARATION_STRICT,
        //SymfonySetList::SYMFONY_52,                         => OK
        //SymfonySetList::SYMFONY_CODE_QUALITY,             => OK
        //SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,    => OK
        //DoctrineSetList::DOCTRINE_ORM_29,                 => Ok
        //DoctrineSetList::DOCTRINE_DBAL_30,
        //DoctrineSetList::DOCTRINE_CODE_QUALITY,           => OK
        //DoctrineSetList::DOCTRINE_25,                     => failed
        //PHPUnitSetList::PHPUNIT_91,
        //PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ];

    //$containerConfigurator->import(SetList::TYPE_DECLARATION);
    $containerConfigurator->import(SetList::PHP_80);

    $services = $containerConfigurator->services();
    //$services->set(VarConstantCommentRector::class);
    $services->set(CombinedAssignRector::class);
    $services->set(SimplifyConditionsRector::class);
    $services->set(SimplifyDeMorganBinaryRector::class);
    $services->set(SimplifyForeachToCoalescingRector::class);
    $services->set(SimplifyIfReturnBoolRector::class);
    $services->set(StrContainsRector::class);
    $services->set(StrEndsWithRector::class);
    $services->set(StrStartsWithRector::class);
    $services->set(CatchExceptionNameMatchingTypeRector::class);
    $services->set(MakeInheritedMethodVisibilitySameAsParentRector::class);
    $services->set(NewlineAfterStatementRector::class);
    $services->set(CatchExceptionNameMatchingTypeRector::class);
    $services->set(ReturnTypeFromStrictTypedCallRector::class);
    $services->set(AddMethodCallBasedStrictParamTypeRector::class);
    $services->set(AddVoidReturnTypeWhereNoReturnRector::class);
    $services->set(ReturnTypeFromStrictTypedPropertyRector::class);
    $services->set(TypedPropertyFromStrictConstructorRector::class);
    $services->set(AddClosureReturnTypeRector::class);
    $services->set(ReturnTypeFromReturnNewRector::class);
    //$services->set(AddArrayReturnDocTypeRector::class); //=> false|... replaced by bool|...
    $services->set(ReturnTypeDeclarationRector::class);
    $services->set(PropertyTypeDeclarationRector::class);
    $services->set(AddArrayParamDocTypeRector::class);
    $services->set(ParamTypeByParentCallTypeRector::class);
    $services->set(ParamTypeByMethodCallTypeRector::class);
    $services->set(ParamTypeDeclarationRector::class);
    $services->set(SimplifyBoolIdenticalTrueRector::class);
    $services->set(RecastingRemovalRector::class);
    $services->set(RemoveAlwaysTrueIfConditionRector::class);
    $services->set(RemoveAndTrueRector::class);
    $services->set(RemoveDeadIfForeachForRector::class);
    $services->set(AddArrayParamDocTypeRector::class);
    $services->set(AddClosureReturnTypeRector::class);
    $services->set(AddMethodCallBasedStrictParamTypeRector::class);
    //$services->set(RemoveNullPropertyInitializationRector::class);
    $services->set(RemoveUselessParamTagRector::class);
    $services->set(BooleanInIfConditionRuleFixerRector::class)
        ->call('configure', [[
            BooleanInIfConditionRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]]);
    $services->set(BooleanInTernaryOperatorRuleFixerRector::class)
        ->call('configure', [[
            BooleanInTernaryOperatorRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]]);
    $services->set(DisallowedEmptyRuleFixerRector::class)
        ->call('configure', [[
            DisallowedEmptyRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]]);
    $services->set(DisallowedShortTernaryRuleFixerRector::class)
        ->call('configure', [[
            DisallowedShortTernaryRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]]);
    //$services->set(NoTwigMissingVariableRule::class)
    //$services->set(ChangeLocalPropertyToVariableRector::class);
    //$services->set(RemoveUselessReturnTagRector::class); // remove array<mixed> OK is useless, need to configure PHPstan before
};
