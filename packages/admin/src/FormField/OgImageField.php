<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OgImageField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('ogImage', TextType::class, [
            'required' => false,
            'label' => 'admin.page.ogImage.label',
            'help_html' => true,
            'help' => 'admin.page.ogImage.help',
        ]);
    }
}
