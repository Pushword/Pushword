<?php

namespace Pushword\Svg\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_APP_FALLBACK = [
        'svg_dir',
    ];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('svg');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->scalarNode('svg_dir')->defaultValue('%vendor_dir%/fortawesome/font-awesome/svgs')->cannotBeEmpty()->end()
        ->end();

        return $treeBuilder;
    }
}
