<?php

namespace Pushword\TemplateEditor;

use Pushword\TemplateEditor\DependencyInjection\TemplateEditorExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordTemplateEditorBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new TemplateEditorExtension();
        }

        return $this->extension;
    }
}
