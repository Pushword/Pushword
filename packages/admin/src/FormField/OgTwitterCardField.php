<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<PageInterface>
 */
class OgTwitterCardField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('twitterCard', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCard.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCard.help',
        ]);
    }
}
