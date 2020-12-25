<?php

namespace Pushword\StaticGenerator\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_APP_FALLBACK = [
        'static_generate_for',
        'static_symlink',
        'static_dir',
        'static_dont_copy',
    ];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('static_generator');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->booleanNode('static_symlink')
                ->info('For github pages, this params is forced to false (need a hard copy).')
                ->defaultTrue()
            ->end()
            ->scalarNode('static_generate_for')
            ->info('For githubPages, set false (need a hard copy).')
                ->defaultValue('apache')
                ->validate()
                    ->ifNotInArray(['apache', 'github'])->thenInvalid('Invalid static_generate_for %s')
                ->end()
            ->end()

            ->scalarNode('static_dir')
                ->defaultValue('')
                ->info('If null or empty, static dir will be /host.tld/.')
            ->end()
            ->arrayNode('static_dont_copy')
                ->info('file or folder in your public dir to avoid to copy in static')
                ->scalarPrototype()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
