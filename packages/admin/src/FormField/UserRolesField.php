<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractField<UserInterface>
 */
class UserRolesField extends AbstractField
{
    /**
     * @param FormMapper<UserInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('roles', ImmutableArrayType::class, [
            'label' => false,
            'keys' => [
                ['0', ChoiceType::class, [
                    'required' => false,
                    'label' => 'admin.user.role.label',
                    'choices' => \in_array('ROLE_SUPER_ADMIN', $this->formFieldManager->user?->getRoles() ?? [], true) ? [
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
