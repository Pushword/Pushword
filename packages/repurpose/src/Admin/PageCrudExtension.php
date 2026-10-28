<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\Entity\Page;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Offers carousel creation for pages with none and shares per-request source counts
 * with the page list and sidebar. Wired only when the admin bundle is installed.
 */
final class PageCrudExtension implements PageCrudExtensionInterface, ResetInterface
{
    /** @var array<string, array<string, int>>|null */
    private ?array $linkedCounts = null;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SocialPostRepository $socialPosts,
    ) {
    }

    public function configureActions(Actions $actions): void
    {
        $repurpose = fn (): Action => Action::new('repurpose', 'repurpose.action.repurpose', 'fas fa-wand-magic-sparkles')
            ->displayIf(fn (Page $page): bool => 0 === $this->linkedCount($page))
            ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                'admin_repurpose_from_page',
                ['id' => (string) $page->id],
            ));

        $actions
            ->add(Crud::PAGE_INDEX, $repurpose())
            ->add(Crud::PAGE_EDIT, $repurpose());
    }

    #[AsTwigFunction('repurpose_linked_carousels_count')]
    public function linkedCount(Page $page): int
    {
        $this->linkedCounts ??= $this->socialPosts->countBySourcePage();

        return $this->linkedCounts[$page->host][$page->slug] ?? 0;
    }

    public function reset(): void
    {
        $this->linkedCounts = null;
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
