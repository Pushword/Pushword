<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class OgDescriptionField extends AbstractField
{
    /**
     * @param FormMapper<Page> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('ogDescription', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.ogDescription.label',
            'help_html' => true,
            'help' => 'admin.page.ogDescription.help',
        ]);
    }
}
