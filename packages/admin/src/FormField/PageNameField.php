<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageNameField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('name', TextareaType::class, [
            'label' => 'admin.page.name.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.name.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }
}
