<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OgTwitterCreatorField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('twitterCreator', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCreator.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCreator.help',
        ]);
    }
}
