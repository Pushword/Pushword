<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgTwitterCreatorField extends AbstractField
{
    /**
     * @param FormMapper<Page> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('twitterCreator', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCreator.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCreator.help',
        ]);
    }
}
