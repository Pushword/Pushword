<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<User>
 */
class UserFirstNameField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('firstname', TextType::class, [
            'required' => false,
            'label' => 'adminUserFirstnameLabel',
        ]);
    }
}
