<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\ContactEvent;
use Pushword\Newsletter\Entity\DeliveryEvent;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Enum\ContactTransition;

/** Load a selection in three queries, independently of its recipient count. */
final class MessageObservations
{
    /** @var array<int, list<ClickEvent>> */
    public array $clicks = [];

    /** @var array<int, list<DeliveryEvent>> */
    public array $deliveries = [];

    /** @var array<int, list<ContactEvent>> */
    public array $unsubscribes = [];

    /** @param list<DeliveryMessage> $messages */
    public function __construct(EntityManagerInterface $entityManager, array $messages)
    {
        if ([] === $messages) {
            return;
        }

        foreach ($entityManager->getRepository(ClickEvent::class)->findBy(['message' => $messages]) as $click) {
            $this->clicks[$click->message->id ?? 0][] = $click;
        }

        foreach ($entityManager->getRepository(DeliveryEvent::class)->findBy(['message' => $messages], ['occurredAt' => 'ASC', 'id' => 'ASC']) as $event) {
            $this->deliveries[$event->message->id ?? 0][] = $event;
        }

        foreach ($entityManager->getRepository(ContactEvent::class)->findBy(['message' => $messages, 'transition' => ContactTransition::Unsubscribe]) as $event) {
            $this->unsubscribes[$event->message->id ?? 0][] = $event;
        }
    }
}
