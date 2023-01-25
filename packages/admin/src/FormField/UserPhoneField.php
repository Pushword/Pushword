<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<UserInterface>
 */
class UserPhoneField extends AbstractField
{
    /**
     * @param FormMapper<UserInterface> $form
     *
     * @return FormMapper<UserInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add(
            'phone',
            TextType::class,
            [
                'required' => false,
                'label' => 'admin.user.phone.label',
            ]
        );
    }
}
