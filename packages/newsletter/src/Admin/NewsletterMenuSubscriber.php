<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class NewsletterMenuSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuItemsEvent::NAME => 'addNewsletterMenuItems'];
    }

    public function addNewsletterMenuItems(AdminMenuItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::subMenu('newsletter.menu.label', 'fas fa-envelope-open-text')->setSubItems([
                MenuItem::linkTo(CampaignCrudController::class, 'newsletter.campaign.label.plural', 'fas fa-paper-plane'),
                MenuItem::linkTo(AutomationCrudController::class, 'newsletter.automation.label.plural', 'fas fa-robot'),
                MenuItem::linkTo(ContactCrudController::class, 'newsletter.contact.label.plural', 'fas fa-address-book'),
                MenuItem::linkTo(AudienceCrudController::class, 'newsletter.audience.label.plural', 'fas fa-users'),
            ]),
            620,
        );
    }
}
