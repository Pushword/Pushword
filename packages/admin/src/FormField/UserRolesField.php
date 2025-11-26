<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Pushword\Core\Entity\User;

/**
 * @extends AbstractField<User>
 */
class UserRolesField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return ChoiceField::new('roles', 'admin.user.role.label')
            ->onlyOnForms()
            ->setChoices($this->getRoleChoices())
            ->allowMultipleChoices()
            ->renderAsNativeWidget()
            ->setFormTypeOption('required', false);
    }

    /**
     * @return array<string, string>
     */
    private function getRoleChoices(): array
    {
        $choices = [
            'admin.user.role.admin' => 'ROLE_ADMIN',
            'admin.user.role.editor' => 'ROLE_EDITOR',
            'admin.user.role.user' => 'ROLE_USER',
        ];

        if (\in_array('ROLE_SUPER_ADMIN', $this->formFieldManager->user?->getRoles() ?? [], true)) {
            return ['admin.user.role.super_admin' => 'ROLE_SUPER_ADMIN'] + $choices;
        }

        return $choices;
    }
}
