<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class PriorityField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'priority',
            NumberType::class,
            [
                'required' => false,
                'label' => 'admin.page.priority.label',
            ]
        );
    }
}
