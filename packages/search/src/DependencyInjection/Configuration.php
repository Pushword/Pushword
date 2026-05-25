<?php

namespace Pushword\Search\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('search');

        $treeBuilder->getRootNode() // @phpstan-ignore-line
            ->children()
                ->scalarNode('index_dir')
                    ->defaultValue('%kernel.project_dir%/var/search')
                    ->info('Local directory holding one Loupe index per host.')
                ->end()
                ->integerNode('results_per_page')
                    ->defaultValue(20)
                ->end()
                ->arrayNode('searchable_attributes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['title', 'h1', 'tags', 'content'])
                    ->info('Indexed full-text fields, ordered by descending weight. Extend it to rank on attributes contributed via SearchDocumentEvent.')
                ->end()
                ->arrayNode('filterable_attributes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['host', 'locale', 'tags'])
                    ->info('Attributes usable in search filters and facets. Add custom fields (e.g. productCode, difficulty, price) contributed via SearchDocumentEvent.')
                ->end()
                ->booleanNode('incremental')
                    ->defaultTrue()
                    ->info('Reindex a page on save/delete through Messenger.')
                ->end()
                ->booleanNode('index_on_static')
                    ->defaultTrue()
                    ->info('Build the index as part of `pw:static`.')
                ->end()
                ->enumNode('static_mode')
                    ->values(['endpoint', 'json', 'both'])
                    ->defaultValue('both')
                    ->info('What the static build emits: a PHP search endpoint, the client-side search.json, or both.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
