<?php

namespace Pushword\Admin\FormField;

use DateTime;
use Pushword\Core\Entity\User;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DatePickerType;

/**
 * @extends AbstractField<User>
 */
class UserDateOfBirthField extends AbstractField
{
    /**
     * @param FormMapper<User> $form
     */
    public function formField(FormMapper $form): void
    {
        $dateTime = new DateTime();

        $form->add(
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
