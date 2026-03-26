<?php

declare(strict_types=1);

namespace Pushword\Admin\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
abstract readonly class AbstractRouteMenuWithHostsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SiteRegistry $siteRegistry,
        private string $label,
        private string $icon,
        private string $routeName,
        private int $weight,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AdminMenuItemsEvent::NAME => 'onMenuItems',
        ];
    }

    public function onMenuItems(AdminMenuItemsEvent $event): void
    {
        $hosts = $this->siteRegistry->getHosts();

        if (\count($hosts) <= 1) {
            $event->addMenuItem(
                MenuItem::linkToRoute($this->label, $this->icon, $this->routeName),
                $this->weight,
            );

            return;
        }

        $subItems = [
            MenuItem::linkToRoute('All', 'fa fa-globe', $this->routeName),
        ];

        foreach ($hosts as $host) {
            $subItems[] = MenuItem::linkToRoute(
                $host,
                'fa fa-globe',
                $this->routeName,
                ['host' => $host],
            );
        }

        $event->addMenuItem(
            MenuItem::subMenu($this->label, $this->icon)->setSubItems($subItems),
            $this->weight,
        );
    }
}
