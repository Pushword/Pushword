<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\User;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<User>
 */
class UserPasswordField extends AbstractField
{
    /**
     * @param FormMapper<User> $form
     */
    public function formField(FormMapper $form): void
    {
        $form
            ->add('plainPassword', TextType::class, [
                'required' => null === $this->admin->getSubject()->getId(),
                'label' => 'admin.user.password.label',
            ]);
    }
}
