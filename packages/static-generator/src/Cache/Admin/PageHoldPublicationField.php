<?php

namespace Pushword\StaticGenerator\Cache\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Entity\Page;
use Pushword\StaticGenerator\StaticAppGenerator;

/**
 * Edit-only switch letting an editor hold an already published page's static
 * file in place while saving edits, so the change stays out of production until
 * the hold is released.
 *
 * @extends AbstractField<Page>
 */
final class PageHoldPublicationField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $page = $this->admin->getSubject();

        // Edit-only: a brand-new page has no generated static file to hold yet.
        if (null === $page->id) {
            return null;
        }

        $app = '' !== $page->host
            ? $this->formFieldManager->apps->findByHost($page->host)
            : $this->formFieldManager->apps->getDefault();

        if (null === $app || ! StaticAppGenerator::isCacheMode($app)) {
            return null;
        }

        return BooleanField::new('holdPublication', 'adminPageHoldPublicationLabel')
            ->setHelp('adminPageHoldPublicationHelp')
            ->renderAsSwitch(true)
            ->onlyOnForms();
    }
}
