<?php

namespace Pushword\Flat\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    final public const array DEFAULT_APP_FALLBACK = [
        'flat_content_dir',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('flat');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')
              ->defaultValue(self::DEFAULT_APP_FALLBACK)
              ->cannotBeEmpty()
            ->end()
            ->scalarNode('flat_content_dir')
              ->defaultValue('%kernel.project_dir%/content/_host_')
            ->end()
            ->scalarNode('content_snapshot_key')
              ->defaultNull()
              ->info('Shared secret granting read-only access to the content snapshot download')
            ->end()
            ->integerNode('change_detection_cache_ttl')
              ->defaultValue(300)
              ->info('Cache TTL in seconds for change detection (default: 5 minutes)')
            ->end()
            ->booleanNode('auto_export_enabled')
              ->defaultTrue()
              ->info('Enable automatic export after admin modifications')
            ->end()
            ->booleanNode('auto_git_commit')
              ->defaultFalse()
              ->info('Automatically git commit content changes after export')
            ->end()
            ->integerNode('export_debounce_delay')
              ->defaultValue(120)
              ->info('Delay in seconds before processing deferred export (default: 120)')
            ->end()
            ->integerNode('lock_ttl')
              ->defaultValue(1800)
              ->info('Default lock TTL in seconds (default: 30 minutes)')
            ->end()
            ->booleanNode('auto_lock_on_flat_changes')
              ->defaultTrue()
              ->info('Automatically lock when flat files are modified')
            ->end()
            ->integerNode('webhook_lock_default_ttl')
              ->defaultValue(3600)
              ->info('Default TTL for webhook locks in seconds (default: 1 hour)')
            ->end()
            ->arrayNode('exclude_files')
              ->scalarPrototype()->end()
              ->defaultValue(['CLAUDE.md', 'README.md'])
              ->info('File basenames to exclude from flat sync import')
            ->end()
            ->arrayNode('notification_email_recipients')
              ->scalarPrototype()->end()
              ->defaultValue([])
              ->info('Email addresses to receive conflict and error notifications')
            ->end()
            ->scalarNode('notification_email_from')
              ->defaultNull()
              ->info('Sender email address for notifications')
            ->end()
            ->arrayNode('ignored_properties')
              ->scalarPrototype()->end()
              ->defaultValue([])
              ->info('Custom property names to exclude from flat file export and import')
            ->end()
            ->scalarNode('default_editor')
              ->defaultNull()
              ->info("Email of the user a page created by import is attributed to when its file names no editor (default: the site's first super admin)")
            ->end()
        ->end();

        return $treeBuilder;
    }
}
