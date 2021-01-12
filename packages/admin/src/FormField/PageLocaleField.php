<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PageLocaleField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('locale', TextType::class, [
            'label' => 'admin.page.locale.label',
            'help_html' => true,
            'help' => 'admin.page.locale.help',
        ]);
    }
}
