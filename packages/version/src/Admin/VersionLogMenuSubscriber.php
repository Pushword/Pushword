<?php

namespace Pushword\Version\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class VersionLogMenuSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuItemsEvent::NAME => 'addMenuItem'];
    }

    public function addMenuItem(AdminMenuItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::linkTo(VersionLogCrudController::class, 'versionActivityLog', 'fa fa-clock-rotate-left'),
            300,
        );
    }
}
