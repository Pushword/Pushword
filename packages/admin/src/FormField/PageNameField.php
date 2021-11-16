<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageNameField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('name', TextareaType::class, [
            'label' => 'admin.page.name.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.name.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }
}
