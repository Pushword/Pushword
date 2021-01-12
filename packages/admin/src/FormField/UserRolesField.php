<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DatePickerType;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserRolesField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('roles', ImmutableArrayType::class, [
            'label' => false,
            'keys' => [
                ['0', ChoiceType::class, [
                    'required' => false,
                    'label' => 'admin.user.role.label',
                    'choices' => \in_array('ROLE_SUPER_ADMIN', $this->admin->getUser()->getRoles()) ? [
                        'admin.user.role.super_admin' => 'ROLE_SUPER_ADMIN',
                        'admin.user.role.admin' => 'ROLE_ADMIN',
                        'admin.user.role.editor' => 'ROLE_EDITOR',
                        'admin.user.role.user' => 'ROLE_USER',
                    ] : [
                        'admin.user.role.admin' => 'ROLE_ADMIN',
                        'admin.user.role.editor' => 'ROLE_EDITOR',
                        'admin.user.role.user' => 'ROLE_USER',
                    ],
                ]],
            ],
        ]);
    }
}
