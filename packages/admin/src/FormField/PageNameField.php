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

class PageNameField extends AbstractField
{

    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('name', TextareaType::class, [
            'label' => 'admin.page.name.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.name.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }
}
