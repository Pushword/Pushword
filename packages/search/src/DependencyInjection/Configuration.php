<?php

declare(strict_types=1);

namespace Pushword\Search\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'search_stop_words',
        'search_matching_strategy',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('search');

        $treeBuilder->getRootNode()
            ->children()
                ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
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
                ->arrayNode('search_stop_words')
                    ->scalarPrototype()->end()
                    ->info('Query words ignored while other words remain (e.g. de, la, les). Compared without case or accents. Overridable per app.')
                ->end()
                ->enumNode('search_matching_strategy')
                    ->values(['any', 'all'])
                    ->defaultValue('any')
                    ->info('any: a page matching one query word is a hit, ranked by how many it matches. all: every query word must match. Overridable per app.')
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
