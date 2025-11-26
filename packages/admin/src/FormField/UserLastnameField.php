<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<User>
 */
class UserLastnameField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('lastname', TextType::class, [
            'required' => false,
            'label' => 'admin.user.lastname.label',
        ]);
    }
}
