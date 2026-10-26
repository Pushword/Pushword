<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Pushword\Core\Entity\User;

/**
 * @extends AbstractField<User>
 */
class UserRolesField extends AbstractField
{
    /** Translation key of each role the admin offers, highest first. */
    public const array ROLE_LABELS = [
        'ROLE_SUPER_ADMIN' => 'adminUserRoleSuperAdmin',
        'ROLE_ADMIN' => 'adminUserRoleAdmin',
        'ROLE_EDITOR' => 'adminUserRoleEditor',
        'ROLE_USER' => 'adminUserRoleUser',
    ];

    public function getEasyAdminField(): ?FieldInterface
    {
        return ChoiceField::new('roles', 'adminUserRoleLabel')
            ->onlyOnForms()
            ->setChoices($this->getRoleChoices())
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setFormTypeOption('required', false);
    }

    /**
     * @return array<string, string>
     */
    private function getRoleChoices(): array
    {
        $labels = self::ROLE_LABELS;
        if (! \in_array('ROLE_SUPER_ADMIN', $this->formFieldManager->getUser()?->getRoles() ?? [], true)) {
            unset($labels['ROLE_SUPER_ADMIN']);
        }

        return array_flip($labels);
    }
}
