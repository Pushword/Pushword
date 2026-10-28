<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class NewsletterMenuSubscriber implements EventSubscriberInterface
{
    public function __construct(private CampaignIndex $campaigns)
    {
    }

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
                MenuItem::linkToRoute('newsletter.statistics.title', 'fas fa-chart-simple', 'admin_newsletter_statistics'),
                MenuItem::linkToRoute('newsletter.campaign.label.plural', 'fas fa-paper-plane', $this->campaigns->route()),
                MenuItem::linkTo(EmailBlockCrudController::class, 'newsletter.block.plural', 'fas fa-cubes'),
                MenuItem::linkTo(AutomationCrudController::class, 'newsletter.automation.label.plural', 'fas fa-robot'),
                MenuItem::linkTo(ContactCrudController::class, 'newsletter.contact.label.plural', 'fas fa-address-book'),
                MenuItem::linkTo(AudienceCrudController::class, 'newsletter.audience.label.plural', 'fas fa-users'),
                MenuItem::linkTo(AutomationDeliveryCrudController::class, 'newsletter.delivery.label.plural', 'fas fa-list-check'),
            ]),
            620,
        );
    }
}
