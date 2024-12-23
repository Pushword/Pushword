<?php

namespace Pushword\Admin\FormField;

use Override;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;

/**
 * @extends CreatedAtField<Page>
 */
class PageCreatedAtField extends CreatedAtField
{
    /**
     * @param FormMapper<Page> $form
     */
    #[Override]
    public function formField(FormMapper $form): void
    {
        $form->add('createdAt', DateTimePickerType::class, [
            'format' => CreatedAtField::DateTimePickerFormat,
            'datepicker_options' => CreatedAtField::DateTimePickerOptions,
            'label' => $this->formFieldManager->getMessagePrefix().'.createdAt.label',
        ]);
    }
}
