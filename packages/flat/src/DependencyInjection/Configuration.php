<?php

namespace Pushword\Flat\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    public const DEFAULT_APP_FALLBACK = [
        'flat_content_dir',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('flat');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->scalarNode('flat_content_dir')->defaultValue('')->end()
        ->end();

        return $treeBuilder;
    }
}
