<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class PageLocaleField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('locale', TextType::class, [
            // Page::$locale defaults to '' — an empty locale means "the site's locale".
            // Leaving the form field required rendered `required` on an input sitting in a
            // collapsed fieldset: the browser refused the submit and could not focus the
            // offending control, so the save button silently did nothing.
            'required' => false,
            'label' => 'adminPageLocaleLabel',
            'help_html' => true,
            'help' => 'adminPageLocaleHelp',
        ]);
    }
}
