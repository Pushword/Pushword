<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

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
            'format' => DateTimeType::HTML5_FORMAT,
            'datepicker_options' => [
                'useCurrent' => true,
            ],
            'label' => $this->admin->getMessagePrefix().'.createdAt.label',
        ]);
    }
}
