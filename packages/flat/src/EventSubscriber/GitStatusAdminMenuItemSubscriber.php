<?php

declare(strict_types=1);

namespace Pushword\Flat\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Flat\Service\GitAutoCommitter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class GitStatusAdminMenuItemSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private GitAutoCommitter $gitAutoCommitter,
        private RouterInterface $router,
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
        if (! $this->gitAutoCommitter->isEnabled()) {
            return;
        }

        $event->addMenuItem(
            MenuItem::linkToUrl('adminLabelGitStatus', 'fa fa-code-branch', $this->router->generate('admin_git_status')),
            350,
        );
    }
}
