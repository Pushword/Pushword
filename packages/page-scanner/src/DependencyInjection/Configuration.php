<?php

namespace Pushword\PageScanner\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_page_scanner');
        $treeBuilder
            ->getRootNode()
                ->children()
                    // ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
                    ->scalarNode('min_interval_between_scan')
                        ->defaultValue('PT5M')->cannotBeEmpty()
                        ->end()
                    ->arrayNode('links_to_ignore')
                        ->prototype('scalar')
                            ->defaultValue(['https://www.example.tld/*'])
                    // ->end()
                // ->end()
        ;

        return $treeBuilder;
    }
}
