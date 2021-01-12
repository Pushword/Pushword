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

class PageImagesField extends AbstractField
{

    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'pageHasMedias',
            CollectionType::class,
            [
                'by_reference' => false,
                'required' => false,
                'label' => ' ',
                'type_options' => [
                    'delete' => true,
                ],
            ],
            [
                'allow_add' => false,
                'allow_delete' => true,
                'btn_add' => false,
                'btn_catalogue' => false,
                'edit' => 'inline',
                'inline' => 'table',
                'sortable' => 'position',
                //'link_parameters' => ['context' => $context],
                'admin_code' => 'pushword.admin.pagehasmedia',
            ]
        );
    }
}
