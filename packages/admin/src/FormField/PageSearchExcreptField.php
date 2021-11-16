<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageSearchExcreptField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('searchExcrept', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.searchExcrept.label',
            'help_html' => true,
            'help' => 'admin.page.searchExcrept.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }
}
