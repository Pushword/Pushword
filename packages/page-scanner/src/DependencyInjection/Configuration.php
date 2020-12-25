<?php

namespace Pushword\PageScanner\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_APP_FALLBACK = [
    ];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('pushword_page_scanner');
        $treeBuilder->getRootNode()->children()
        ->end();

        return $treeBuilder;
    }
}
