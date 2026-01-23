<?php

namespace Pushword\Admin\FormField;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\DateType;

/**
 * @extends AbstractField<User>
 */
class UserDateOfBirthField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $dateTime = new DateTime();

        return $this->buildEasyAdminField('dateOfBirth', DateType::class, [
            'widget' => 'single_text',
            'html5' => true,
            'required' => false,
            'label' => 'adminUserDateOfBirthLabel',
            'attr' => [
                'min' => '1900-01-01',
                'max' => $dateTime->format('Y-m-d'),
            ],
        ]);
    }
}
