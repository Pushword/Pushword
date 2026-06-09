<?php

namespace Pushword\Api\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_api');
        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('delete_strategy')
                    ->values(['soft', 'hard'])
                    ->defaultValue('hard')
                ->end()
                ->scalarNode('soft_delete_workflow_state')
                    ->defaultValue('archived')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
