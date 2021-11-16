<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * @template T of object
 * @extends AbstractField<T>
 */
class PriorityField extends AbstractField
{
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add(
            'priority',
            NumberType::class,
            [
                'required' => false,
                'label' => 'admin.page.priority.label',
            ]
        );
    }
}
