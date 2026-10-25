<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Symfony\Component\Uid\Uuid;

/** One transport attempt, with the consent snapshot and identities used in its links. */
#[ORM\Entity]
#[ORM\Table(name: 'newsletter_delivery_message')]
class DeliveryMessage implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    public private(set) string $reference;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $attemptedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public private(set) ?string $transportError = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    public private(set) ?string $messageId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public private(set) ?string $transportMessageId = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Contact $contact,
        #[ORM\Column(type: Types::BOOLEAN)]
        public private(set) bool $trackingAllowed,
        #[ORM\ManyToOne(targetEntity: Campaign::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        public private(set) ?Campaign $campaign = null,
        #[ORM\ManyToOne(targetEntity: Automation::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        public private(set) ?Automation $automation = null,
        #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
        public private(set) ?string $stepId = null,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        public private(set) ?int $position = null,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        public private(set) ?int $occurrenceId = null,
    ) {
        $this->reference = Uuid::v7()->toRfc4122();
        $this->attemptedAt = new DateTimeImmutable();
    }

    public function identify(string $messageId): void
    {
        $this->messageId = $messageId;
    }

    public function accept(?string $transportMessageId): void
    {
        $this->acceptedAt = new DateTimeImmutable();
        $this->transportMessageId = $transportMessageId;
    }

    public function fail(string $reason): void
    {
        $this->transportError = mb_substr($reason, 0, 255);
    }

    public function moveTo(Contact $contact): void
    {
        $this->contact = $contact;
    }
}
