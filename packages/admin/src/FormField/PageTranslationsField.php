<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageTranslationsField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('translations', ModelAutocompleteType::class, [
            'required' => false,
            'multiple' => true,
            'class' => $this->admin->getModelClass(),
            'property' => 'slug',
            'label' => 'admin.page.translations.label',
            'help_html' => true,
            'help' => 'admin.page.translations.help',
            'btn_add' => false,
            'to_string_callback' => static fn (PageInterface $entity): string => $entity->getLocale().' ('.$entity->getSlug().')',
        ]);
    }
}
