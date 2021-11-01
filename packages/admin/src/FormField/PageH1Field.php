<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageH1Field extends AbstractField
{
    public const DEFAULT_STYLE = 'font-size: 30px !important; border:0;'
            .'font-weight: 700; padding: 10px 10px 0px 10px; margin:-23px auto; max-width: 640px; color:#111827';

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
