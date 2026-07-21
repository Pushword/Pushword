<?php

namespace Pushword\Repurpose\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SocialPostMenuSubscriber implements EventSubscriberInterface
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
            MenuItem::linkTo(SocialPostCrudController::class, 'repurpose.label.plural', 'fas fa-share-nodes'),
            670,
        );
    }
}
