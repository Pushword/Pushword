<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<PageInterface>
 */
class OgTitleField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('ogTitle', TextType::class, [
            'label' => 'admin.page.ogTitle.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.ogTitle.help',
        ]);
    }
}
