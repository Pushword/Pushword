<?php

namespace Pushword\TemplateEditor\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('template_editor');
        $treeBuilder->getRootNode()->children()
            ->booleanNode('disable_creation')->defaultFalse()->end()
            ->variableNode('can_be_edited_list')->defaultValue([])->end()
        ->end();

        return $treeBuilder;
    }
}
