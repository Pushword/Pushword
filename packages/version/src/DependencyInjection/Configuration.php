<?php

namespace Pushword\Version\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_version');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('storage_dir')
                    ->defaultValue('%kernel.project_dir%/var/log/version')
                    ->cannotBeEmpty()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
