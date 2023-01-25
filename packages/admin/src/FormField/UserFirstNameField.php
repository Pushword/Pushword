<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<UserInterface>
 */
class UserFirstNameField extends AbstractField
{
    /**
     * @param FormMapper<UserInterface> $form
     *
     * @return FormMapper<UserInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add(
            'firstname',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.firstname.label',
            ]
        );
    }
}
