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

class PageMainImageField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainImage', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->admin->getMediaClass(),
            //'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
        ]);
    }
}
