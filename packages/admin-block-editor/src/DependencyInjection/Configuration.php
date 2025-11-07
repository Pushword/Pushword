<?php

namespace Pushword\AdminBlockEditor\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'admin_block_editor',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('admin_block_editor');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->booleanNode('new_page')->defaultValue(true)->info('Set false to disable block editor for new page')->end()
            ->booleanNode('admin_block_editor')->defaultValue(true)->info('set false to disable block editor (and get the default Mardown Editor)')->end()
        ->end();

        return $treeBuilder;
    }
}
