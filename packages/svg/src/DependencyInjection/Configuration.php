<?php

namespace Pushword\Svg\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const DEFAULT_APP_FALLBACK = [
        'svg_dir',
    ];

    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('svg');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->variableNode('svg_dir')->defaultValue([
                '%kernel.project_dir%/templates/icons',
                '%vendor_dir%/fortawesome/font-awesome/svgs/solid',
                '%vendor_dir%/fortawesome/font-awesome/svgs/regular',
                '%vendor_dir%/fortawesome/font-awesome/svgs/brands',
            ])->cannotBeEmpty()->end()
        ->end();

        return $treeBuilder;
    }
}
