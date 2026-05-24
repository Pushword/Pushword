<?php

namespace Pushword\Snippet\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SnippetMenuSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuItemsEvent::NAME => 'addSnippetMenuItem'];
    }

    public function addSnippetMenuItem(AdminMenuItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::linkTo(SnippetCrudController::class, 'snippet.label.plural', 'fas fa-puzzle-piece'),
            650,
        );
    }
}
