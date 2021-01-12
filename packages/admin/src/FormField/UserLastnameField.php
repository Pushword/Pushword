<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserLastnameField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'lastname',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.lastname.label',
            ]
        );
    }
}
