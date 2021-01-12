<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\CollectionType;

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
