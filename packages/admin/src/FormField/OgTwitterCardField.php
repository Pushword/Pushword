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
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('twitterCard', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCard.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCard.help',
        ]);
    }
}
