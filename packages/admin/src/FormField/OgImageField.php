<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<PageInterface>
 */
class OgImageField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('ogImage', TextType::class, [
            'required' => false,
            'label' => 'admin.page.ogImage.label',
            'help_html' => true,
            'help' => 'admin.page.ogImage.help',
        ]);
    }
}
