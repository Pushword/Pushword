<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\User;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<User>
 */
class UserLastnameField extends AbstractField
{
    /**
     * @param FormMapper<User> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add(
            'lastname',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.lastname.label',
            ]
        );
    }
}
