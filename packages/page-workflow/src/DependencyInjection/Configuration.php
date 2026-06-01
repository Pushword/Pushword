<?php

namespace Pushword\PageWorkflow\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_page_workflow');

        $treeBuilder
            ->getRootNode()
            ->children()
                ->booleanNode('editorial_workflow')
                    ->defaultTrue()
                    ->info('When false, the page_editorial workflow is not registered and the admin workflow UI is hidden.')
                ->end()
                ->booleanNode('require_approval_before_publish')
                    ->defaultFalse()
                    ->info('When true, a page can only be published (publishedAt) once its editorial workflowState is "approved".')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
