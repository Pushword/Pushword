<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Admin;

use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Core\Entity\Page;
use Pushword\Repurpose\FormField\PageCarouselsField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PageCarouselSidebarSubscriber implements EventSubscriberInterface
{
    public function __construct(private PageCrudExtension $links, private TranslatorInterface $translator)
    {
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [FormEvent::NAME => 'addSidebar'];
    }

    /** @param FormEvent<object> $event */
    public function addSidebar(FormEvent $event): void
    {
        $page = $event->getAdmin()->getSubject();
        if (! $page instanceof Page || null === $page->id) {
            return;
        }

        $count = $this->links->linkedCount($page);
        if (0 === $count) {
            return;
        }

        $fields = $event->getFields();
        $grouped = ! array_is_list($fields[1]);
        $event->getFormFieldManager()->requestStack->getCurrentRequest()?->attributes->set('repurpose_sidebar_has_heading', $grouped);
        if ($grouped) {
            $heading = $this->translator->trans('repurposeLinkedCarouselsCount', ['%count%' => $count]);
            $fields[1] = [$heading => ['fields' => [PageCarouselsField::class], 'expand' => true]] + $fields[1];
        } else {
            array_unshift($fields[1], PageCarouselsField::class);
        }

        $event->setFields($fields);
    }
}
