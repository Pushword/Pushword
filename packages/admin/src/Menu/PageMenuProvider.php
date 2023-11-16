<?php

namespace Pushword\Admin\Menu;

use Doctrine\Persistence\ManagerRegistry;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Sonata\AdminBundle\Event\ConfigureMenuEvent;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageMenuProvider implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public const ORDER_NUMBER = 'priority';

    #[Required]
    public \Pushword\Core\Component\App\AppPool $apps;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public ManagerRegistry $doctrine;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public TranslatorInterface $translator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public RequestStack $requestStack;

    public function __construct(private readonly FactoryInterface $factory)
    {
    }

    public function getMenu(): ItemInterface
    {
        $factory = $this->factory;
        $hosts = $this->apps->getHosts();

        $menu = $factory->createItem('content', [
            'label' => $this->translator->trans('admin.label.content'),
            'route' => 'admin_page_list',
            'name' => 'page',
        ])
            // ->setCurrent(true) // dirty hack to prevent bug
            // ->setExtra('keep_open', true)
            ->setExtra(self::ORDER_NUMBER, 1)
        ;

        $pageMenu = $menu; // $menu->addChild('admin.label.page', ['route' => 'admin_page_list'])->setExtra(self::ORDER_NUMBER, 1);

        $isRequesteingRedirection = $this->isRequestingRedirection();
        if (\count($hosts) > 1) {
            $pageMenu->setCurrent(true);
            foreach ($hosts as $host) {
                $hostMenu = $pageMenu->addChild($host, [
                    'route' => 'admin_page_list',
                    'routeParameters' => ['filter[host][value][]' => $host],
                ]);
                if (! $this->isRequestingPageEdit($host)) {
                    continue;
                }

                if ($isRequesteingRedirection) {
                    continue;
                }

                $hostMenu->setCurrent(true);
            }
        }

        // if ($this->isRequestingPageEdit() && ! $isRequesteingRedirection) {
        //     $pageMenu->setCurrent(true);
        // }

        $menu->addChild('admin.label.cheatsheet', ['route' => 'cheatsheetEditRoute']);

        return $menu;
    }

    public function getRedirectionMenu(): ItemInterface
    {
        $factory = $this->factory;
        $hosts = $this->apps->getHosts();

        $menu = $factory->createItem('redirection', [
            'label' => $this->translator->trans('admin.label.redirection'),
            'route' => 'admin_redirection_list',
        ])
            ->setExtra(self::ORDER_NUMBER, 3)
        ;

        $redirectionMenu = $menu; // $menu->addChild('admin.label.redirection', ['route' => 'admin_redirection_list'])->setExtra(self::ORDER_NUMBER, 3);

        $isRequesteingRedirection = $this->isRequestingRedirection();
        if (\count($hosts) > 1) {
            foreach ($hosts as $host) {
                $hostMenu = $redirectionMenu->addChild($host, [
                    'route' => 'admin_redirection_list',
                    'routeParameters' => ['filter[host][value][]' => $host],
                ]);
                if (! $this->isRequestingPageEdit($host)) {
                    continue;
                }

                if (! $isRequesteingRedirection) {
                    continue;
                }

                $hostMenu->setCurrent(true);
            }
        } elseif ($this->isRequestingPageEdit() && $isRequesteingRedirection) {
            $redirectionMenu->setCurrent(true);
        }

        return $menu;
    }

    #[AsEventListener(event: 'sonata.admin.event.configure.menu.sidebar')]
    public function reOrderMenu(ConfigureMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->reorderMenuItems($menu);
    }

    private function getPriority(ItemInterface $menuItem): ?int
    {
        if ('admin.label.media' === $menuItem->getLabel()) {
            return 2;
        }

        $priority = $menuItem->getExtra(self::ORDER_NUMBER);

        return \is_int($priority) ? $priority : null;
    }

    /**
     * Inspire by priority concept from Router
     * https://github.com/symfony/routing/blob/6.3/RouteCollection.php#L104.
     */
    public function reorderMenuItems(ItemInterface $menu): void
    {
        $priorities = [];
        $menuItems = $menu->getChildren();
        $menuItemsNameList = [];
        foreach ($menuItems as $key => $menuItem) {
            if ($menuItem->hasChildren()) {
                $this->reorderMenuItems($menuItem);
            }

            $priority = $this->getPriority($menuItem);
            if (! \in_array($priority, [null, 0], true)) {
                $priorities[$key] = $priority;
            }

            $menuItemsNameList[$key] = $menuItem->getName();
        }

        if ([] !== $priorities) {
            $keysOrder = array_flip(array_keys($menuItemsNameList));
            uksort($menuItemsNameList, static fn ($n1, $n2): int => (($priorities[$n1] ?? 1_000_000) <=> ($priorities[$n2] ?? 1_000_000)) ?: ($keysOrder[$n1] <=> $keysOrder[$n2])); // @phpstan-ignore-line
        }

        $menu->reorderChildren($menuItemsNameList);

        return;
    }

    private function isRequestingRedirection(): bool
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return false;
        }

        return str_starts_with($this->requestStack->getCurrentRequest()->attributes->getString('_route'), 'admin_redirection');
    }

    private function isRequestingPageEdit(string $host = ''): bool
    {
        if ('' === $host) {
            return null !== $this->apps->getCurrentPage();
        }

        if (null !== $this->apps->getCurrentPage() && $this->apps->getCurrentPage()->getHost() === $host) {
            return true;
        }

        if (($request = $this->requestStack->getCurrentRequest()) === null) {
            return false;
        }

        if (($hostInRequest = $this->extractHostFilter($request->query->all())) === null) {
            return false;
        }

        return $hostInRequest === $host;
    }

    /**
     * @param mixed[] $query
     */
    private function extractHostFilter(array $query): ?string
    {
        if (
            isset($query['filter']) && \is_array($query['filter'])
            && isset($query['filter']['host']) && \is_array($query['filter']['host'])
            && isset($query['filter']['host']['value'])) {
            if (\is_array($query['filter']['host']['value']) && isset($query['filter']['host']['value'][0]) && \is_string($query['filter']['host']['value'][0])) {
                return $query['filter']['host']['value'][0];
            }

            if (\is_string($query['value'])) {
                return $query['value'];
            }
        }

        return null;
    }
}
