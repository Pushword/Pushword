<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageMainImageField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('mainImage', ModelListType::class, [
            'required' => false,
            'class' => $this->formFieldManager->mediaClass,
            'label' => ' ', // 'admin.page.mainImage.label',
            'btn_edit' => false,
        ]);
    }
}
