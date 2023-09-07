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
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('createdAt', DateTimePickerType::class, [
            'format' => "yyyy-MM-dd'T'HH:mm",
            'datepicker_options' => CreatedAtField::DateTimePickerOptions,
            'label' => $this->admin->getMessagePrefix().'.createdAt.label',
        ]);
    }
}
