<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<PageInterface>
 */
class PageImageFormField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        $form->add('inline_image', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->admin->getMediaClass(),
            // 'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => 'pushword.admin.media',
        ]);

        return $form->add('inline_attaches', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->admin->getMediaClass(),
            // 'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => 'pushword.admin.media',
        ]);
    }
}
