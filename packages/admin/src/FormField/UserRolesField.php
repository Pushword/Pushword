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
        return ChoiceField::new('roles', 'adminUserRoleLabel')
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
            'adminUserRoleAdmin' => 'ROLE_ADMIN',
            'adminUserRoleEditor' => 'ROLE_EDITOR',
            'adminUserRoleUser' => 'ROLE_USER',
        ];

        if (\in_array('ROLE_SUPER_ADMIN', $this->formFieldManager->user?->getRoles() ?? [], true)) {
            return ['adminUserRoleSuperAdmin' => 'ROLE_SUPER_ADMIN'] + $choices;
        }

        return $choices;
    }
}
