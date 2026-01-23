<?php

namespace Pushword\AdminBlockEditor\Form;

use Override;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class EditorjsType extends TextareaType
{
    #[Override]
    public function getBlockPrefix(): string
    {
        return 'editorjs';
    }
}
