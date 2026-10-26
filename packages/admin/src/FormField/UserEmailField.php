<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

/**
 * @extends AbstractField<User>
 */
class UserEmailField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('email', EmailType::class, ['label' => 'adminUserEmailLabel']);
    }
}
