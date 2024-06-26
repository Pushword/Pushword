<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\AbstractField;
use Pushword\Admin\MediaAdmin;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;

/**
 * @extends AbstractField<Page>
 */
class PageImageFormField extends AbstractField
{
    public function formField(FormMapper $form): void
    {
        $form->add('inline_image', ModelListType::class, [
            'required' => false,
            'class' => Media::class,
            // 'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => MediaAdmin::class,
        ]);

        $form->add('inline_attaches', ModelListType::class, [
            'required' => false,
            'class' => Media::class,
            // 'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
            'mapped' => false,
            'row_attr' => ['style' => 'display:none'],
        ], [
            'admin_code' => MediaAdmin::class,
        ]);
    }
}
