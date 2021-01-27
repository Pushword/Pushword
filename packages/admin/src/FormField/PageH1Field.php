<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageH1Field extends AbstractField
{
    const DEFAULT_STYLE = 'border-radius: 5px; font-size: 140%; border: 1px solid #ddd;'
            .'font-weight: 700; padding: 10px 10px 0px 10px;margin-top:-23px; margin-bottom:-23px';

    public function formField(FormMapper $formMapper, string $style = ''): FormMapper
    {
        $style = $style ?: self::DEFAULT_STYLE;

        // Todo move style to view
        return $formMapper->add('h1', TextareaType::class, [
            'required' => false,
            'attr' => ['class' => 'autosize textarea-no-newline', 'placeholder' => 'admin.page.title.label', 'style' => $style],
            'label' => ' ',
        ]);
    }
}
