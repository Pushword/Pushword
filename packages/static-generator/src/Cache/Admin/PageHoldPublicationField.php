<?php

namespace Pushword\StaticGenerator\Cache\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Entity\Page;

/**
 * Edit-only switch letting an editor hold an already published page's static
 * file in place while saving edits, so the change stays out of production until
 * the hold is released. Honoured by every generatePage chokepoint, so it applies
 * to both the full static export (`pw:static`) and `cache: static` mode; the
 * field exists whenever the static-generator bundle is installed.
 *
 * @extends AbstractField<Page>
 */
final class PageHoldPublicationField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        // Edit-only: a brand-new page has no generated static file to hold yet.
        if (null === $this->admin->getSubject()->id) {
            return null;
        }

        return BooleanField::new('holdPublication', 'adminPageHoldPublicationLabel')
            ->setHelp('adminPageHoldPublicationHelp')
            ->renderAsSwitch(true)
            ->onlyOnForms();
    }
}
