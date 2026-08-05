<?php

namespace Pushword\LinkImprover\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * `< 1` caps the total of in-content links to a ratio of the word count,
     * `>= 1` is an absolute cap. One link per 50 words stays well under the
     * density Wikipedia keeps in its running prose (1 per 20 words, measured
     * over 185k words), while leaving room above what a well linked page
     * already holds — the cap counts the existing links.
     */
    final public const float DEFAULT_MAX_LINKS = 0.02;

    final public const array DEFAULT_APP_FALLBACK = [
        'link_improver',
        'link_improver_max_links',
        'link_improver_ignored_urls',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_link_improver');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')
              ->defaultValue(self::DEFAULT_APP_FALLBACK)
              ->cannotBeEmpty()
            ->end()
            ->booleanNode('link_improver')
              ->defaultFalse()
              ->info("Insert internal links into rendered content, from the pages' names. Opt-in per app (or globally here): it rewrites content.")
            ->end()
            ->floatNode('link_improver_max_links')
              ->defaultValue(self::DEFAULT_MAX_LINKS)
              ->info('Cap on the TOTAL of in-content links, existing ones included. Below 1 it is a ratio of the word count (0.02 = one link per 50 words); 1 or more is an absolute count.')
            ->end()
            ->arrayNode('link_improver_ignored_urls')
              ->scalarPrototype()->end()
              ->defaultValue([])
              ->info("URLs never offered as a link target, written as the report prints them ('/' for the homepage, '/slug' otherwise). The homepage is the usual candidate: its name is often the brand, written on nearly every page.")
            ->end()
        ->end();

        return $treeBuilder;
    }
}
