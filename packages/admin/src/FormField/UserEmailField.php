<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;

/**
 * @extends AbstractField<User>
 */
class UserEmailField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('email', null, ['label' => 'adminUserEmailLabel']);
    }
}
