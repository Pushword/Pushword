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

class PageH1Field extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        $style = 'border-radius: 5px; font-size: 140%; font-weight: 700;'
            .'border: 1px solid #ddd; padding: 10px 10px 0px 10px;margin-top:-23px; margin-bottom:-23px';
        // Todo move style to view
        return $formMapper->add('h1', TextareaType::class, [
            'required' => false,
            'attr' => ['class' => 'autosize textarea-no-newline', 'placeholder' => 'admin.page.title.label', 'style' => $style],
            'label' => ' ',
        ]);
    }
}
