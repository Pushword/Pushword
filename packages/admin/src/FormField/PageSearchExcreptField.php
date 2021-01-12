<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageSearchExcreptField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('searchExcrept', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.searchExcrept.label',
            'help_html' => true,
            'help' => 'admin.page.searchExcrept.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }
}
