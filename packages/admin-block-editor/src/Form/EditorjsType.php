<?php

namespace Pushword\AdminBlockEditor\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class EditorjsType extends TextType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'editorjs';
    }
}
