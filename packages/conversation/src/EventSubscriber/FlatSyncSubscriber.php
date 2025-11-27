<?php

namespace Pushword\Conversation\EventSubscriber;

use Pushword\Conversation\Flat\ConversationSync;
use Pushword\Flat\Event\FlatSyncCompletedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class FlatSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ConversationSync $conversationSync,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FlatSyncCompletedEvent::class => 'onFlatSyncCompleted',
        ];
    }

    public function onFlatSyncCompleted(FlatSyncCompletedEvent $event): void
    {
        $this->conversationSync->sync($event->getHost());
    }
}
