<?php

namespace Pushword\LinkImprover\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    final public const array DEFAULT_APP_FALLBACK = [
        'link_improver',
        'link_improver_max_links',
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
              ->defaultValue(0.02)
              ->info('Cap on the TOTAL of in-content links, existing ones included. Below 1 it is a ratio of the word count (0.02 = one link per 50 words); 1 or more is an absolute count.')
            ->end()
        ->end();

        return $treeBuilder;
    }
}
