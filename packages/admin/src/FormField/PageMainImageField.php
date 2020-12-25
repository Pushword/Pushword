<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;

/**
 * @extends AbstractField<Page>
 */
class PageMainImageField extends AbstractField
{
    /**
     * @param FormMapper<Page> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('mainImage', ModelListType::class, [
            'required' => false,
            'class' => Media::class,
            'label' => ' ', // 'admin.page.mainImage.label',
            'btn_edit' => false,
        ]);
    }
}
