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
        'main_image_formats',
    ];

    /**
     * @var array<string, int>
     */
    final public const array DEFAULT_MAIN_IMAGE_FORMATS = [
        'adminPageMainImageFormatNone' => 1,
        'adminPageMainImageFormatNormal' => 0,
        'adminPageMainImageFormat13fullscreen' => 2,
        'adminPageMainImageFormat34fullscreen' => 3,
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_advanced_main_image');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->booleanNode('advanced_main_image')->defaultValue(true)->info('Set false to disable extension')->end()
            ->variableNode('main_image_formats')
                ->info('Available main image formats (key = translation label, value = numeric value)')
                ->defaultValue(self::DEFAULT_MAIN_IMAGE_FORMATS)
            ->end()
        ->end();

        return $treeBuilder;
    }
}
