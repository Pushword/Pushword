<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\Form\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
