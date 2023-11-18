<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\AbstractField;
use Pushword\Admin\MediaAdmin;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<PageInterface>
 */
class PageImageFormField extends AbstractField
{
    public function formField(FormMapper $form): void
    {
        $form->add('inline_image', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->formFieldManager->mediaClass,
            // 'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => MediaAdmin::class,
        ]);

        $form->add('inline_attaches', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->formFieldManager->mediaClass,
            // 'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => MediaAdmin::class,
        ]);
    }
}
