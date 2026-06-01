<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Override;
use Pushword\Admin\Form\Type\RedirectFromRowType;
use Pushword\Core\Entity\Page;

/**
 * Rich editor for a page's internal redirects (Jekyll redirect_from style): a collection
 * of old-path + HTTP-code rows, with add/remove buttons. Backed by the page's redirectFrom
 * map through the virtual redirectFromRows accessor.
 *
 * @extends AbstractField<Page>
 */
class PageRedirectFromField extends AbstractField
{
    #[Override]
    public function getEasyAdminField(): FieldInterface
    {
        return CollectionField::new('redirectFromRows', 'adminPageRedirectFromLabel')
            ->onlyOnForms()
            ->setEntryType(RedirectFromRowType::class)
            ->setEntryIsComplex()
            ->allowAdd()
            ->allowDelete()
            ->setHelp('adminPageRedirectFromHelp')
            ->setFormTypeOption('help_html', true)
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('required', false);
    }
}
