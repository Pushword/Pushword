<?php

namespace Pushword\TemplateEditor\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class AdminMenuItemSubscriber implements EventSubscriberInterface
{
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
            MenuItem::linkToRoute('Template Editor', 'fa fa-code', 'admin_template_editor_list'),
            200
        );
    }
}
