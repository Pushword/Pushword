<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @template T of object
 *
 * @extends AbstractField<T>
 */
class CustomPropertiesField extends AbstractField
{
    /**
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('standAloneCustomProperties', TextareaType::class, [
            'required' => false,
            'attr' => [
                'style' => 'width:100%; height:100px;min-height:15vh',
                // 'data-editor' => 'yaml',
                'class' => 'autosize',
            ],
            // 'label' => $this->admin->getMessagePrefix().'.customProperties.label',
            'label' => 'admin.page.customProperties.label',
            'help_html' => true,
            'help' => 'admin.page.customProperties.help',
            // 'help' => $this->admin->getMessagePrefix().'.customProperties.help',
        ]);
    }
}
