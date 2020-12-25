<?php

namespace Pushword\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DatePickerType;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

trait UserAdminFormFieldsTrait
{
    protected function configureFormFieldEmail(FormMapper $formMapper): FormMapper
    {
        return $formMapper
            ->add('email', null, ['label' => 'admin.user.email.label']);
    }

    protected function configureFormFieldUsername(FormMapper $formMapper): FormMapper
    {
        return $formMapper
                ->add('username', null, ['label' => 'admin.user.username.label']);
    }

    protected function configureFormFieldPassword(FormMapper $formMapper): FormMapper
    {
        return $formMapper
        ->add('plainPassword', TextType::class, [
            'required' => (! $this->getSubject() || null === $this->getSubject()->getId()),
            'label' => 'admin.user.password.label',
        ]);
    }

    protected function configureFormFieldDateOfBirth(FormMapper $formMapper): FormMapper
    {
        $now = new \DateTime();

        return $formMapper->add(
            'dateOfBirth',
            DatePickerType::class,
            [
                'years' => range(1900, $now->format('Y')),
                'dp_min_date' => '1-1-1900',
                'dp_max_date' => $now->format('c'),
                'required' => false,
                'label' => 'admin.user.dateOfBirth.label',
            ]
        );
    }

    protected function configureFormFieldFirstName(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'firstname',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.firstname.label',
            ]
        );
    }

    protected function configureFormFieldLastName(FormMapper $formMapper): FormMapper
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

    protected function configureFormFieldCity(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'city',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.city.label',
            ]
        );
    }

    protected function configureFormFieldPhone(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'phone',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.phone.label',
            ]
        );
    }

    protected function configureFormFieldRoles(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('roles', ImmutableArrayType::class, [
            'label' => false,
            'keys' => [
                ['0', ChoiceType::class, [
                    'required' => false,
                    'label' => 'admin.user.role.label',
                    'choices' => $this->getUser()->hasRole('ROLE_SUPER_ADMIN') ? [
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
