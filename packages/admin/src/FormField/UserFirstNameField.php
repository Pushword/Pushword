<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\User;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<User>
 */
class UserFirstNameField extends AbstractField
{
    /**
     * @param FormMapper<User> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add(
            'firstname',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.firstname.label',
            ]
        );
    }
}
