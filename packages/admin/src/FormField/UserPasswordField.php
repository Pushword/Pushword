<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<User>
 */
class UserPasswordField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('plainPassword', TextType::class, [
            'required' => null === $this->admin->getSubject()->getId(),
            'label' => 'adminUserPasswordLabel',
        ]);
    }
}
