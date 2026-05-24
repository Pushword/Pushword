<?php

namespace Pushword\Snippet;

use Pushword\Snippet\Attribute\AsSnippet;
use Pushword\Snippet\Component\SnippetComponentInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordSnippetBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(SnippetComponentInterface::class)
            ->addTag('pushword.snippet');

        $container->registerAttributeForAutoconfiguration(
            AsSnippet::class,
            static function (ChildDefinition $definition): void {
                $definition->addTag('pushword.snippet');
            }
        );
    }
}
