<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class CustomPropertiesField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('standAloneCustomProperties', TextareaType::class, [
            'required' => false,
            'attr' => [
                'style' => 'width:100%; height:100px;min-height:15vh',
                //'data-editor' => 'yaml',
                'class' => 'autosize',
            ],
            'label' => $this->admin->getMessagePrefix().'.customProperties.label',
            'help_html' => true,
            'help' => $this->admin->getMessagePrefix().'.customProperties.help',
        ]);
    }
}
