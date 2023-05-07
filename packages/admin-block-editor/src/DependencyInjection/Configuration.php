<?php

namespace Pushword\AdminBlockEditor\DependencyInjection;

use Pushword\AdminBlockEditor\Block\DefaultBlock;
use Pushword\AdminBlockEditor\BlockEditorFilter;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const DEFAULT_APP_FALLBACK = [
        'admin_block_editor',
        'admin_block_editor_disable_listener',
        'admin_block_editor_blocks',
        'admin_block_editor_type_to_prose',
    ];

    /**
     * @var string[]
     */
    final public const DEFAULT_TYPE_TO_PROSE = ['paragraph', 'image', 'list', 'blockquote', 'code', 'delimiter', 'header'];

    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('admin_block_editor');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->booleanNode('new_page')->defaultValue(true)->info('Set false to disable block editor for new page')->end()
            ->booleanNode('admin_block_editor_type_to_prose')->defaultValue(self::DEFAULT_TYPE_TO_PROSE)->end()
            ->booleanNode('admin_block_editor')->defaultValue(true)->info('set false to disable block editor (and get the default Mardown Editor)')->end()
            ->booleanNode('admin_block_editor_disable_listener')->defaultValue(false)
                ->info('set true if you prefer to use filter then add '.(BlockEditorFilter::class))
                ->end()
            ->variableNode('admin_block_editor_blocks')->defaultValue(DefaultBlock::AVAILABLE_BLOCKS)
                ->info('you can set wich block to activate via an array. Eg: ["paragraph", "Pushword\AdminBlockEditor\Block\Example", ...].')
                ->end()
        ->end();

        return $treeBuilder;
    }
}
