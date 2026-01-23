<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * @template T of object
 *
 * @extends AbstractField<T>
 */
class WeightField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('weight', NumberType::class, [
            'required' => false,
            'label' => 'adminPageWeightLabel',
        ]);
    }
}
