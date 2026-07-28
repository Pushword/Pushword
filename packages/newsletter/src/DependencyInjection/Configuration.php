<?php

namespace Pushword\Newsletter\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Site properties this bundle reads through SiteConfig, so an `apps:` entry
     * can override them per host.
     *
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'newsletter_possible_origins',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('newsletter');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
                    ->scalarNode('newsletter_possible_origins')
                        ->defaultNull()
                        ->info('Regex matching the origins allowed to POST the subscribe endpoint cross-domain (a statically generated site posting to its live host). Falls back to the conversation setting when null.')
                    ->end()
                    ->integerNode('send_batch')
                        ->defaultValue(50)
                        ->info('Maximum number of mails a single `pw:newsletter:tick` run may send.')
                    ->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
