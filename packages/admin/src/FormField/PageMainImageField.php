<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;

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
