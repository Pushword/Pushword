<?php

namespace Pushword\PageUpdateNotifier\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'page_update_notification_from',
        'page_update_notification_to',
        'page_update_notification_interval',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_page_update_notifier');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->scalarNode('page_update_notification_from')->defaultValue(null)->end()
            ->scalarNode('page_update_notification_to')->defaultValue(null)->end()
            ->scalarNode('page_update_notification_interval')->defaultValue('PT6H')->end()
        ->end();

        return $treeBuilder;
    }
}
