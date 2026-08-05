<?php

namespace Pushword\PageScanner\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_page_scanner');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->scalarNode('min_interval_between_scan')
                        ->defaultValue('PT5M')->cannotBeEmpty()
                        ->end()
                    ->integerNode('external_url_cache_ttl')
                        ->defaultValue(86400)
                        ->min(0)
                        ->info('TTL in seconds for external URL cache (0 = disabled, default: 86400 = 24h)')
                        ->end()
                    ->integerNode('external_url_failure_cache_ttl')
                        ->defaultValue(3600)
                        ->min(0)
                        ->info('TTL in seconds for a URL that failed. Shorter than the success TTL: a fixed link should stop being reported soon, and a scan run while the network is down must not poison the whole corpus for a day (default: 3600 = 1h)')
                        ->end()
                    ->integerNode('parallel_batch_size')
                        ->defaultValue(50)
                        ->min(1)
                        ->max(200)
                        ->info('Number of external URLs to check in parallel per batch')
                        ->end()
                    ->integerNode('url_check_timeout_ms')
                        ->defaultValue(10000)
                        ->min(1000)
                        ->info('Timeout in milliseconds for each external URL check')
                        ->end()
                    ->booleanNode('skip_external_url_check')
                        ->defaultFalse()
                        ->info('Skip external URL validation entirely for faster scans')
                        ->end()
                    ->arrayNode('links_to_ignore')
                        ->prototype('scalar')
                            ->defaultValue(['https://www.example.tld/*', '/admin/*'])
                        ->end()
                    ->end()
                    ->arrayNode('errors_to_ignore')
                        ->prototype('scalar')->end()
                        ->defaultValue([])
                        ->info('Errors to ignore, matched against the error code then its message. Format: "pattern" (global) or "host/slug: pattern" (per-route). Supports fnmatch wildcards.')
        ;

        return $treeBuilder;
    }
}
