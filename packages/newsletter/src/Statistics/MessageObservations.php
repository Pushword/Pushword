<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Statistics;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\ContactEvent;
use Pushword\Newsletter\Entity\DeliveryEvent;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Enum\ContactTransition;

/** Load a selection in three queries per chunk of messages, never one per recipient. */
final class MessageObservations
{
    /** Keeps each IN() list under every database's bound-parameter limit, including SQLite's 999 before 3.32. */
    public const int CHUNK = 500;

    /** @var array<int, list<ClickEvent>> */
    public array $clicks = [];

    /** @var array<int, list<DeliveryEvent>> */
    public array $deliveries = [];

    /** @var array<int, list<ContactEvent>> */
    public array $unsubscribes = [];

    /** @param list<DeliveryMessage> $messages */
    public function __construct(EntityManagerInterface $entityManager, array $messages)
    {
        foreach (array_chunk($messages, self::CHUNK) as $chunk) {
            foreach ($entityManager->getRepository(ClickEvent::class)->findBy(['message' => $chunk]) as $click) {
                $this->clicks[$click->message->id ?? 0][] = $click;
            }

            foreach ($entityManager->getRepository(DeliveryEvent::class)->findBy(['message' => $chunk], ['occurredAt' => 'ASC', 'id' => 'ASC']) as $event) {
                $this->deliveries[$event->message->id ?? 0][] = $event;
            }

            foreach ($entityManager->getRepository(ContactEvent::class)->findBy(['message' => $chunk, 'transition' => ContactTransition::Unsubscribe]) as $event) {
                $this->unsubscribes[$event->message->id ?? 0][] = $event;
            }
        }
    }
}
