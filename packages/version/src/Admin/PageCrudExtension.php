<?php

namespace Pushword\Version\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds a per-row "review changes" action to the page list. It jumps straight to
 * the version compare view against the most relevant baseline: the last live
 * (not-held) version when the page is on hold — so the diff shows exactly what
 * the hold is keeping back — otherwise the previous revision. Wired only when
 * the admin bundle is installed (see config/services.php).
 */
final readonly class PageCrudExtension implements PageCrudExtensionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureActions(Actions $actions): void
    {
        $action = Action::new('versionReview', 'versionReviewChanges', 'fa fa-code-compare')
            ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                'admin_version_review',
                ['type' => 'page', 'id' => (string) $page->id],
            ));

        $actions->add(Crud::PAGE_INDEX, $action);
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
