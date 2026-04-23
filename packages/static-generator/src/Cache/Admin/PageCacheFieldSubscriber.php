<?php

namespace Pushword\StaticGenerator\Cache\Admin;

use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PagePublishedAtField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Inserts PageCacheField into the "State" fieldset, right after publishedAt.
 */
final class PageCacheFieldSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'addCacheField',
        ];
    }

    /**
     * @param FormEvent<object> $event
     */
    public function addCacheField(FormEvent $event): void
    {
        if (! $event->getAdmin() instanceof PageCrudController) {
            return;
        }

        $fields = $event->getFields();
        $group = $fields[1]['adminPageStateLabel'] ?? null;
        if (! \is_array($group)) {
            return;
        }

        $insertAfter = array_search(PagePublishedAtField::class, $group, true);
        /* @phpstan-ignore function.impossibleType */
        if (\is_int($insertAfter)) {
            array_splice($group, $insertAfter + 1, 0, [PageCacheField::class]);
        } else {
            $group[] = PageCacheField::class;
        }

        $fields[1]['adminPageStateLabel'] = $group;
        /* @phpstan-ignore argument.type */
        $event->setFields($fields);
    }
}
