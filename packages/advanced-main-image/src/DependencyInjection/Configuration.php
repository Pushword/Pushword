<?php

namespace Pushword\AdvancedMainImage\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'advanced_main_image',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('admin_block_editor');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->booleanNode('advanced_main_image')->defaultValue(true)->info('Set false to disable extesion')->end()
        ->end();

        return $treeBuilder;
    }
}
