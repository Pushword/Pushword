<?php

namespace Pushword\Repurpose\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds a "Repurpose" action to the page list and edit screen. It routes to
 * {@see RepurposeFromPageController}, which drafts a carousel and opens the studio
 * for a page with none, or lists its carousels when it already has some. Wired only
 * when the admin bundle is installed (see config/services.php).
 */
final readonly class PageCrudExtension implements PageCrudExtensionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureActions(Actions $actions): void
    {
        $repurpose = Action::new('repurpose', 'repurpose.action.repurpose', 'fas fa-wand-magic-sparkles')
            ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                'admin_repurpose_from_page',
                ['id' => (string) $page->id],
            ));

        $actions
            ->add(Crud::PAGE_INDEX, $repurpose)
            ->add(Crud::PAGE_EDIT, $repurpose);
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
