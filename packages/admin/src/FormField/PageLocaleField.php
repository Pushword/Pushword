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
        $this->fillDefaultLocale($this->admin->getSubject());

        return $this->buildEasyAdminField('locale', TextType::class, [
            // Page::$locale defaults to '' and the whole stack falls back to the site's
            // locale, so the field must never be required: rendering `required` on an
            // input that sits in a collapsed fieldset makes the browser refuse the submit
            // without being able to focus it — the save button then silently does nothing.
            'required' => false,
            'label' => 'adminPageLocaleLabel',
            'help_html' => true,
            'help' => 'adminPageLocaleHelp',
        ]);
    }

    /**
     * PageCrudController fills the locale of a page created from the admin, and
     * PageResolver falls back to the site's locale when rendering — but a page imported
     * by the flat sync or written through the API keeps `Page::$locale` at ''. Show the
     * locale that is actually in effect instead of an empty box; saving then persists it.
     *
     * Resolved from the page's own host, not the ambient site: on a multi-host install
     * each host declares its own locale (fr, en-US…), and the edit screen may well be
     * served under a different one.
     */
    private function fillDefaultLocale(Page $page): void
    {
        if ('' !== $page->locale) {
            return;
        }

        $page->locale = $this->formFieldManager->apps->get($page->host)->getLocale();
    }
}
