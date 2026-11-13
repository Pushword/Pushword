<?php

declare(strict_types=1);

namespace Pushword\TwigStanTool;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;

/** Resolves PHP getView() calls for the same site as the Twig analysis loader. */
final class ResolveViewReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private ?SiteConfig $site = null;

    /** @param class-string $className */
    public function __construct(private readonly string $className)
    {
    }

    public function getClass(): string
    {
        return $this->className;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return 'getView' === $methodReflection->getName();
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $call = ArgumentsNormalizer::reorderMethodArguments(
            ParametersAcceptorSelector::selectFromArgs($scope, $methodCall->getArgs(), $methodReflection->getVariants()),
            $methodCall,
        );
        if (! $call instanceof MethodCall) {
            return null;
        }

        $arguments = $call->getArgs();
        $path = isset($arguments[0]) ? $scope->getType($arguments[0]->value) : null;
        $fallback = isset($arguments[1]) ? $scope->getType($arguments[1]->value) : new ConstantStringType('@Pushword');
        $paths = $path?->getConstantStrings() ?? [];
        $fallbacks = $fallback->getConstantStrings();
        if ($path instanceof Type && ! $path->isNull()->yes() && (! $path->isString()->yes() || 1 !== \count($paths))) {
            return null;
        }

        if (! $fallback->isString()->yes() || 1 !== \count($fallbacks)) {
            return null;
        }

        return new ConstantStringType($this->getSite()->getView([] === $paths ? null : $paths[0]->getValue(), $fallbacks[0]->getValue()));
    }

    private function getSite(): SiteConfig
    {
        if ($this->site instanceof SiteConfig) {
            return $this->site;
        }

        $kernel = require __DIR__.'/twig-loader.php';
        $registry = $kernel->getContainer()->get('test.service_container')->get(SiteRegistry::class);

        return $this->site = $registry->get(getenv('PUSHWORD_TWIGSTAN_HOST') ?: null);
    }
}
