<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * @template T of object
 *
 * @extends AbstractField<T>
 */
class PriorityField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('priority', NumberType::class, [
            'required' => false,
            'label' => 'admin.page.priority.label',
        ]);
    }
}
