<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Delivery;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Enum\DeliveryEventKind;
use Pushword\Newsletter\Service\ContactManager;

final readonly class DeliveryEventRecorder
{
    public function __construct(private EntityManagerInterface $entityManager, private ContactManager $contacts)
    {
    }

    /** Returns false for a replay, including concurrent deliveries of the same event. */
    public function record(DeliveryNotification $notification): bool
    {
        $message = $this->entityManager->getRepository(DeliveryMessage::class)->findOneBy(['reference' => $notification->messageReference]);
        if (null === $message) {
            throw new InvalidArgumentException('Unknown newsletter message reference.');
        }

        if ('' === $notification->source || '' === $notification->eventId || mb_strlen($notification->source) > 100 || mb_strlen($notification->eventId) > 255) {
            throw new InvalidArgumentException('A delivery event needs a source and a provider event identifier.');
        }

        $connection = $this->entityManager->getConnection();
        $key = hash('sha256', json_encode([$notification->source, $notification->eventId], \JSON_THROW_ON_ERROR));

        try {
            return $connection->transactional(function () use ($connection, $message, $key, $notification): bool {
                if (false !== $connection->fetchOne('SELECT id FROM newsletter_delivery_event WHERE event_key = ?', [$key])) {
                    return false;
                }

                $connection->insert('newsletter_delivery_event', [
                    'message_id' => $message->id,
                    'event_key' => $key,
                    'source' => $notification->source,
                    'external_id' => $notification->eventId,
                    'kind' => $notification->kind->value,
                    'occurred_at' => $notification->occurredAt->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'),
                    'recorded_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
                    'reason' => $notification->reason,
                ]);
                if (DeliveryEventKind::Failed === $notification->kind) {
                    $this->contacts->markBounced($message->contact, $notification->source, $message);
                }

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
