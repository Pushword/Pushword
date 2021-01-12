<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class PageParentPageField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('parentPage', EntityType::class, [
            'class' => $this->admin->getPageClass(),
            'label' => 'admin.page.parentPage.label',
            'required' => false,
        ]);
    }
}
