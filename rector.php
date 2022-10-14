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
use Rector\Core\Bootstrap\RectorConfigsResolver;
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
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();

    $rectorConfig->paths(array_map(
            function ($path) { return __DIR__.'/'.$path; },
            array_values(\Safe\json_decode(\Safe\file_get_contents('composer.json'), true)['autoload']['psr-4']) // @phpstan-ignore-line
        ));

    //$parameters->rule(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_80);
    //$containerConfigurator->import(SetList::PHP_80);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        // SetList::CODE_QUALITY,
        // SetList::DEAD_CODE,
        // SetList::CODING_STYLE,
        // SetList::TYPE_DECLARATION,
        // SetList::TYPE_DECLARATION_STRICT,
        // SetList::NAMING,
        // SetList::PRIVATIZATION,
        // SetList::EARLY_RETURN,
        // PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
    $rectorConfig->rule(CombinedAssignRector::class);
    $rectorConfig->rule(SimplifyConditionsRector::class);
    $rectorConfig->rule(SimplifyDeMorganBinaryRector::class);
    $rectorConfig->rule(SimplifyForeachToCoalescingRector::class);
    $rectorConfig->rule(SimplifyIfReturnBoolRector::class);
    $rectorConfig->rule(StrContainsRector::class);
    $rectorConfig->rule(StrEndsWithRector::class);
    $rectorConfig->rule(StrStartsWithRector::class);
    $rectorConfig->rule(CatchExceptionNameMatchingTypeRector::class);
    $rectorConfig->rule(MakeInheritedMethodVisibilitySameAsParentRector::class);
    $rectorConfig->rule(NewlineAfterStatementRector::class);
    $rectorConfig->rule(CatchExceptionNameMatchingTypeRector::class);
    $rectorConfig->rule(AddMethodCallBasedStrictParamTypeRector::class);
    $rectorConfig->rule(AddVoidReturnTypeWhereNoReturnRector::class);
    $rectorConfig->rule(ReturnTypeFromStrictTypedPropertyRector::class);
    $rectorConfig->rule(TypedPropertyFromStrictConstructorRector::class);
    $rectorConfig->rule(AddClosureReturnTypeRector::class);
    $rectorConfig->rule(ReturnTypeFromReturnNewRector::class);
    $rectorConfig->rule(ReturnTypeDeclarationRector::class);
    $rectorConfig->rule(PropertyTypeDeclarationRector::class);
    $rectorConfig->rule(AddArrayParamDocTypeRector::class);
    $rectorConfig->rule(ParamTypeByParentCallTypeRector::class);
    $rectorConfig->rule(ParamTypeByMethodCallTypeRector::class);
    $rectorConfig->rule(ParamTypeDeclarationRector::class);
    //$rectorConfig->rule(SimplifyBoolIdenticalTrueRector::class);
    $rectorConfig->rule(RecastingRemovalRector::class);
    $rectorConfig->rule(RemoveAlwaysTrueIfConditionRector::class);
    $rectorConfig->rule(RemoveAndTrueRector::class);
    $rectorConfig->rule(RemoveDeadIfForeachForRector::class);
    $rectorConfig->rule(AddArrayParamDocTypeRector::class);
    $rectorConfig->rule(AddClosureReturnTypeRector::class);
    $rectorConfig->rule(AddMethodCallBasedStrictParamTypeRector::class);
    //$rectorConfig->rule(RemoveNullPropertyInitializationRector::class);
    $rectorConfig->rule(RemoveUselessParamTagRector::class);
    $rectorConfig->ruleWithConfiguration(BooleanInTernaryOperatorRuleFixerRector::class, [
            BooleanInTernaryOperatorRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]);
    $rectorConfig->ruleWithConfiguration(DisallowedEmptyRuleFixerRector::class, [
            DisallowedEmptyRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]);
    $rectorConfig->ruleWithConfiguration(DisallowedShortTernaryRuleFixerRector::class, [
            DisallowedShortTernaryRuleFixerRector::TREAT_AS_NON_EMPTY => false,
        ]);
};
