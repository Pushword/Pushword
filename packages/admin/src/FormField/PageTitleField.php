<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageTitleField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('title', TextareaType::class, [
            'label' => 'admin.page.title.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.title.help',
            'attr' => ['class' => 'titleToMeasure autosize textarea-no-newline'],
        ]);
    }
}
