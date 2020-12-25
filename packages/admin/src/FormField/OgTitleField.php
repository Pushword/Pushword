<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgTitleField extends AbstractField
{
    /**
     * @param FormMapper<Page> $form
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
