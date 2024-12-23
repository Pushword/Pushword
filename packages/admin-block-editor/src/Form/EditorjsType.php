<?php

namespace Pushword\AdminBlockEditor\Form;

use Override;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class EditorjsType extends TextType
{
    #[Override]
    public function getBlockPrefix(): string
    {
        return 'editorjs';
    }
}
