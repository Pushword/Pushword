<?php

declare(strict_types=1);

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
            ->setEntryToStringMethod($this->rowLabel(...))
            ->allowAdd()
            ->allowDelete()
            ->setHelp('adminPageRedirectFromHelp')
            ->setFormTypeOption('help_html', true)
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('required', false);
    }

    /**
     * Collapsed label for one row. Rows are plain arrays, and EasyAdmin's default
     * stringifier renders any array as "Array (2 items)".
     */
    private function rowLabel(mixed $row): string
    {
        if (! \is_array($row)) {
            return '…';
        }

        $from = \is_string($row['from'] ?? null) ? trim($row['from']) : '';

        if ('' === $from) {
            return '…';
        }

        $code = $row['code'] ?? null;

        return $from.' → '.(is_numeric($code) ? (int) $code : 301);
    }
}
