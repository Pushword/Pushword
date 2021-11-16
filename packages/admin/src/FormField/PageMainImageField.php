<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<PageInterface>
 */
class PageMainImageField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        $form->add('mainImage', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->admin->getMediaClass(),
            'label' => ' ', //'admin.page.mainImage.label',
            'btn_edit' => false,
        ]);

        return $form;
    }
}
