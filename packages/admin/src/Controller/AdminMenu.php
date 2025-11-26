<?php

namespace Pushword\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\CrudMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\SubMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @docs to extend or modify AdminMenu, see https://pushword.com/docs/extension/admin-menu
 */
final readonly class AdminMenu
{
    public function __construct(
        private AppPool $apps,
        private AdminContextProviderInterface $adminContextProvider,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return iterable<int, array{weight: int, item: MenuItemInterface}>
     *
     * @phpstan-return iterable<int, array{weight: int, item: MenuItemInterface}>
     */
    private function collectMenuItems(): iterable
    {
        yield [
            'weight' => 1000,
            'item' => $this->buildPageMenu(),
        ];

        yield [
            'weight' => 900,
            'item' => $this->buildRedirectionMenu(),
        ];

        yield [
            'weight' => 800,
            'item' => MenuItem::linkToCrud('admin.label.media', 'fas fa-images', Media::class),
        ];

        yield [
            'weight' => 700,
            'item' => MenuItem::linkToCrud('admin.label.users', 'fas fa-users', User::class),
        ];

        yield [
            'weight' => 500,
            'item' => MenuItem::section('admin.label.tools'),
        ];
    }

    /**
     * Configure the admin menu items.
     *
     * This method dispatches the {@see AdminMenuItemsEvent} event to allow bundles and end users
     * to customize the menu items. You can listen to this event in two ways:
     *
     * ## Adding an item
     *
     * Create an EventSubscriber that listens to `AdminMenuItemsEvent::NAME` and use `addMenuItem()`:
     *
     * ```php
     * use Pushword\Admin\Menu\AdminMenuItemsEvent;
     * use Symfony\Component\EventDispatcher\EventSubscriberInterface;
     *
     * class MyMenuSubscriber implements EventSubscriberInterface
     * {
     *     public static function getSubscribedEvents(): array
     *     {
     *         return [AdminMenuItemsEvent::NAME => 'onMenuItems'];
     *     }
     *
     *     public function onMenuItems(AdminMenuItemsEvent $event): void
     *     {
     *         $event->addMenuItem(
     *             MenuItem::linkToRoute('My Custom Page', 'fa fa-star', 'my_route'),
     *             500 // weight (higher = appears first)
     *         );
     *     }
     * }
     * ```
     *
     * ## Editing the full menu
     *
     * To completely replace the menu, use `setItems()` in your subscriber:
     *
     * ```php
     * public function onMenuItems(AdminMenuItemsEvent $event): void
     * {
     *     $items = $event->getItems();
     *     // Modify $items array as needed
     *     $event->setItems($items);
     * }
     * ```
     *
     * @return iterable<int, MenuItemInterface>
     *
     * @phpstan-return iterable<int, MenuItemInterface>
     */
    public function configureMenuItems(): iterable
    {
        $items = iterator_to_array($this->collectMenuItems());

        // Dispatcher l'événement pour permettre aux bundles et utilisateurs de personnaliser le menu
        $event = new AdminMenuItemsEvent();
        foreach ($items as $entry) {
            $event->addMenuItem($entry['item'], $entry['weight']);
        }

        $this->eventDispatcher->dispatch($event, AdminMenuItemsEvent::NAME);

        // Récupérer les items depuis l'événement (peuvent avoir été modifiés par les listeners)
        $items = $event->getItems();

        // Trier par poids (ordre décroissant : poids élevé = affiché en premier)
        usort($items, static fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

        // Retourner les items triés
        foreach ($items as $entry) {
            yield $entry['item'];
        }
    }

    private function buildPageMenu(): SubMenuItem
    {
        $hosts = $this->apps->getHosts();

        $listItem = MenuItem::linkToCrud('admin.label.list', 'fas fa-list', Page::class)
            ->setController(PageCrudController::class);
        $subItems = [$listItem];

        if (\count($hosts) > 1) {
            $listItem->setCssClass('d-none');
            foreach ($hosts as $host) {
                $subItems[] = $this->createHostMenuItem($host, PageCrudController::class);
            }
        }

        $cheatSheetItem = MenuItem::linkToRoute('admin.label.cheatsheet', 'fa fa-book', 'cheatsheetEditRoute')
            ->setCssClass('opacity-75');

        if ($this->isCheatSheetActive()) {
            $cheatSheetItem->getAsDto()->setSelected(true);
        }

        $subItems[] = $cheatSheetItem;

        return MenuItem::subMenu('admin.label.content', 'fas fa-file')
            ->setSubItems($subItems);
    }

    private function buildRedirectionMenu(): MenuItemInterface
    {
        $hosts = $this->apps->getHosts();
        if (\count($hosts) <= 1) {
            return MenuItem::linkToCrud('admin.label.redirection', 'fa fa-random', Page::class)
                ->setController(PageRedirectionCrudController::class);
        }

        $listItem = MenuItem::linkToCrud('admin.label.list', 'fas fa-list', Page::class)
            ->setCssClass('d-none')
            ->setController(PageRedirectionCrudController::class);
        $subItems = [$listItem];

        foreach ($hosts as $host) {
            $subItems[] = $this->createHostMenuItem($host, PageRedirectionCrudController::class);
        }

        return MenuItem::subMenu('admin.label.redirection', 'fa fa-random')
            ->setSubItems($subItems);
    }

    private function createHostMenuItem(string $host, string $controller): CrudMenuItem
    {
        $menuItem = MenuItem::linkToCrud($host, 'fa fa-globe', Page::class)
            ->setController($controller)
            ->setQueryParameter('filters[host]', [
                'comparison' => '=',
                'value' => $host,
            ]);

        if ($this->isHostActive($host, $controller)) {
            $menuItem->getAsDto()->setSelected(true);
        }

        return $menuItem;
    }

    private function isHostActive(string $host, string $controller): bool
    {
        $context = $this->adminContextProvider->getContext();

        if (null === $context) {
            return false;
        }

        $crud = $context->getCrud();
        if (null === $crud || $crud->getControllerFqcn() !== $controller) {
            return false;
        }

        /** @var array<string, mixed> $filters */
        $filters = $context->getRequest()->query->all(EA::FILTERS);
        $filteredHost = \is_array($filters['host'] ?? null) ? ($filters['host']['value'] ?? null) : null;
        if (\is_string($filteredHost)) {
            return $filteredHost === $host;
        }

        $entityDto = $context->getEntity();
        $entity = $entityDto->getInstance();
        if (! \is_object($entity) || ! method_exists($entity, 'getHost')) {
            return false;
        }

        return $entity->getHost() === $host;
    }

    private function isCheatSheetActive(): bool
    {
        $context = $this->adminContextProvider->getContext();
        if (null !== $context) {
            $crud = $context->getCrud();
            if (null !== $crud && PageCheatSheetCrudController::class === $crud->getControllerFqcn()) {
                return true;
            }
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        if ('cheatsheetEditRoute' === $request->attributes->get('_route')) {
            return true;
        }

        $path = $request->getPathInfo();

        return str_starts_with($path, '/admin/page-cheat-sheet');
    }
}
