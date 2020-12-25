<?php

namespace Pushword\PageUpdateNotifier\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_APP_FALLBACK = [
        'notifier_email',
        'page_update_notification_mail',
        'interval',
    ];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('pushword_page_update_notifier');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->scalarNode('notifier_email')->defaultValue(null)->end()
            ->scalarNode('page_update_notification_mail')->defaultValue(null)->end()
            ->scalarNode('interval')->defaultValue('P1D')->end()
        ->end();

        return $treeBuilder;
    }
}
