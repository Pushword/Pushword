<?php

namespace Pushword\Quiz\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class QuizResultMenuSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuItemsEvent::NAME => 'addQuizResultMenuItem'];
    }

    public function addQuizResultMenuItem(AdminMenuItemsEvent $event): void
    {
        $event->addMenuItem(
            MenuItem::linkTo(QuizResultCrudController::class, 'quiz.result.label.plural', 'fas fa-circle-question'),
            660,
        );
    }
}
