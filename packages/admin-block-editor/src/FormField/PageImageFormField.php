<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\AbstractField;
use Sonata\AdminBundle\Form\FormMapper;

class PageImageFormField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('inline_image', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->admin->getMediaClass(),
            //'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => 'pushword.admin.media',
        ]);
    }
}
