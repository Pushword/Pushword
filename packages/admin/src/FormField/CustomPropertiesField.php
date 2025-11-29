<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @template T of object
 *
 * @extends AbstractField<T>
 */
class CustomPropertiesField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('standAloneCustomProperties', TextareaType::class, [
            'required' => false,
            'attr' => [
                'style' => 'width:100%; height:100px;min-height:15vh;font-size:10px',
                'data-editor' => 'yaml',
            ],
            'label' => 'adminPageCustomPropertiesLabel',
            'help_html' => true,
            'help' => 'adminPageCustomPropertiesHelp',
        ]);
    }
}
