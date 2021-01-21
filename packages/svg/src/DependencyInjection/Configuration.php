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
            ->variableNode('svg_dir')->defaultValue([
                '%vendor_dir%/fortawesome/font-awesome/svgs/solid',
                '%vendor_dir%/fortawesome/font-awesome/svgs/regular',
                '%vendor_dir%/fortawesome/font-awesome/svgs/brands',
            ])->cannotBeEmpty()->end()
        ->end();

        return $treeBuilder;
    }
}
