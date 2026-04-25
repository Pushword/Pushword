<?php

namespace Pushword\Admin\Menu;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
abstract readonly class AbstractRouteMenuItemSubscriber implements EventSubscriberInterface
{
    public function __construct(
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
        $event->addMenuItem(
            MenuItem::linkToRoute($this->label, $this->icon, $this->routeName),
            $this->weight,
        );
    }
}
