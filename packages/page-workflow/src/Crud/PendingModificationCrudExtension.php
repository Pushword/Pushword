<?php

namespace Pushword\PageWorkflow\Crud;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the pending-modification entry points to PageCrudController:
 *  - "Edit pending" on every published page, available always.
 *  - "Compare pending" shown when a pending modification exists.
 */
final readonly class PendingModificationCrudExtension implements PageCrudExtensionInterface
{
    public function __construct(
        private PendingModificationStorageInterface $storage,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureActions(Actions $actions): void
    {
        $editPending = Action::new('pending_edit', 'adminPagePendingEditLabel')
            ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                'pushword_page_pending_edit',
                ['id' => $page->id],
            ))
            ->displayIf(static fn (Page $page): bool => null !== $page->publishedAt && $page->publishedAt <= new DateTime());

        $compare = Action::new('pending_compare', 'adminPagePendingCompareLabel')
            ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                'pushword_page_pending_compare',
                ['id' => $page->id],
            ))
            ->displayIf(fn (Page $page): bool => $this->storage->has($page));

        $actions->add(Crud::PAGE_EDIT, $editPending);
        $actions->add(Crud::PAGE_EDIT, $compare);
        $actions->add(Crud::PAGE_INDEX, $compare);
    }

    public function configureFilters(Filters $filters): void
    {
    }

    public function configureFields(string $pageName): iterable
    {
        return [];
    }
}
