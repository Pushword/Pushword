<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;

/**
 * @extends AbstractField<User>
 */
class UserUsernameField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('username', null, ['label' => 'admin.user.username.label']);
    }
}
