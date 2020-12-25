<?php

namespace Pushword\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

trait SharedFormFieldsTrait
{
    protected function configureFormFieldCreatedAt(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('createdAt', DateTimePickerType::class, [
            'format' => DateTimeType::HTML5_FORMAT,
            'dp_side_by_side' => true,
            'dp_use_current' => true,
            'dp_use_seconds' => false,
            'dp_collapse' => true,
            'dp_calendar_weeks' => false,
            'dp_view_mode' => 'days',
            'dp_min_view_mode' => 'days',
            'label' => $this->messagePrefix.'.createdAt.label',
        ]);
    }

    protected function configureFormFieldCustomProperties(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('standAloneCustomProperties', TextareaType::class, [
            'required' => false,
            'attr' => [
                'style' => 'width:100%; height:100px;min-height:15vh',
                //'data-editor' => 'yaml',
                'class' => 'autosize',
            ],
            'label' => $this->messagePrefix.'.customProperties.label',
            'help_html' => true,
            'help' => $this->messagePrefix.'.customProperties.help',
        ]);
    }
}
