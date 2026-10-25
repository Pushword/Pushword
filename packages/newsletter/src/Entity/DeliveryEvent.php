<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Enum\DeliveryEventKind;

#[ORM\Entity]
#[ORM\Table(name: 'newsletter_delivery_event')]
class DeliveryEvent implements IdInterface
{
    use IdTrait;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: DeliveryMessage::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) DeliveryMessage $message,
        /** SHA-256 of the provider and its event identifier, shared by all retries. */
        #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
        public private(set) string $eventKey,
        #[ORM\Column(type: Types::STRING, length: 100)]
        public private(set) string $source,
        #[ORM\Column(type: Types::STRING, length: 255)]
        public private(set) string $externalId,
        #[ORM\Column(type: Types::STRING, length: 20, enumType: DeliveryEventKind::class)]
        public private(set) DeliveryEventKind $kind,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public private(set) DateTimeImmutable $occurredAt,
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $reason,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public private(set) DateTimeImmutable $recordedAt,
    ) {
    }
}
