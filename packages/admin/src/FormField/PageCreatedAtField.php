<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;

/**
 * @extends CreatedAtField<PageInterface>
 */
class PageCreatedAtField extends CreatedAtField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('createdAt', DateTimePickerType::class, [
            'format' => CreatedAtField::DateTimePickerFormat,
            'datepicker_options' => CreatedAtField::DateTimePickerOptions,
            'label' => $this->formFieldManager->getMessagePrefix().'.createdAt.label',
        ]);
    }
}
