<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class OgDescriptionField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('ogDescription', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.ogDescription.label',
            'help_html' => true,
            'help' => 'admin.page.ogDescription.help',
        ]);
    }
}
