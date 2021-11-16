<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DatePickerType;

/**
 * @extends AbstractField<UserInterface>
 */
class UserDateOfBirthField extends AbstractField
{
    /**
     * @param FormMapper<UserInterface> $form
     *
     * @return FormMapper<UserInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        $dateTime = new \DateTime();

        return $form->add(
            'dateOfBirth',
            DatePickerType::class,
            [
                'years' => range(1900, $dateTime->format('Y')),
                'dp_min_date' => '1-1-1900',
                'dp_max_date' => $dateTime->format('c'),
                'required' => false,
                'label' => 'admin.user.dateOfBirth.label',
            ]
        );
    }
}
