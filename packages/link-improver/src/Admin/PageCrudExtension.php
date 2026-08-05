<?php

namespace Pushword\LinkImprover\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds an "Auto links" action to the page edit screen, routing to
 * {@see LinkImproverPageController}. Edit only, not the index: the panel renders
 * the page to answer, which is a per-page question rather than a column.
 * Wired only when the admin bundle is installed (see config/services.php).
 */
final readonly class PageCrudExtension implements PageCrudExtensionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureActions(Actions $actions): void
    {
        $action = Action::new('linkImproverAutoLinks', 'linkImproverAutoLinks', 'fa fa-link')
            ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                'admin_link_improver_page',
                ['id' => (string) $page->id],
            ));

        $actions->add(Crud::PAGE_EDIT, $action);
    }

    public function configureFilters(Filters $filters): void
    {
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    public function configureFields(string $pageName): iterable
    {
        return [];
    }
}
