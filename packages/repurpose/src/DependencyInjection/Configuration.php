<?php

namespace Pushword\Repurpose\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('repurpose');
        $treeBuilder->getRootNode()->children()
            ->scalarNode('font_dir')
              ->defaultValue('%kernel.project_dir%/var/repurpose/fonts')
              ->info('Where `pw:repurpose:fonts` installs TTFs (checked before the bundled fonts). App-side so a composer update never wipes them.')
            ->end()
            ->scalarNode('chromium_binary')
              ->defaultNull()
              ->info('Chromium/Chrome binary used to rasterise the preview contact sheet; null auto-detects from PATH.')
            ->end()
            ->scalarNode('ffmpeg_binary')
              ->defaultNull()
              ->info('ffmpeg binary used to encode the slideshow video (.mp4) export; null auto-detects from PATH.')
            ->end()
        ->end();

        return $treeBuilder;
    }
}
