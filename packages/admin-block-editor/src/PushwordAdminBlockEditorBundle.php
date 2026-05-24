<?php

namespace Pushword\AdminBlockEditor;

use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordAdminBlockEditorBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(EditorJsToolProviderInterface::class)
            ->addTag('pushword.editorjs_tool_provider');
    }
}
