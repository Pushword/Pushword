<?php

namespace Pushword\TemplateEditor\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_APP_FALLBACK = [
    ];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('pushword_template_editor');
        $treeBuilder->getRootNode()->children()
        ->end();

        return $treeBuilder;
    }
}
