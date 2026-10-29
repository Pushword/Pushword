<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

/**
 * @extends AbstractField<User>
 */
class UserPasswordField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $isNew = null === $this->admin->getSubject()->id;

        return $this->buildEasyAdminField('plainPassword', PasswordType::class, [
            'required' => false,
            'label' => 'adminUserPasswordLabel',
            'help' => $isNew ? 'adminUserPasswordNewHelp' : 'adminUserPasswordHelp',
            'attr' => ['autocomplete' => 'new-password'],
        ]);
    }
}
