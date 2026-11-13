<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Enum\SmsState;
use Symfony\Component\Uid\Uuid;

/** A frozen SMS and its stable provider identity, including tests and uncertain attempts. */
#[ORM\Entity]
#[ORM\Table(name: 'newsletter_sms_send')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_sms_recipient', columns: ['campaign_id', 'contact_id'])]
#[ORM\Index(name: 'idx_newsletter_sms_quota', columns: ['quota_reserved_at'])]
class SmsSend implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    public private(set) string $reference;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: SmsState::class)]
    public SmsState $state = SmsState::Pending;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    public ?string $gatewayState = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $quotaReservedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $nextAttemptAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $checkedAt = null;

    /** Technical errors deliberately exclude provider responses, credentials and recipients. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $error = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Campaign::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        public private(set) ?Campaign $campaign,
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        public ?Contact $contact,
        #[ORM\Column(type: Types::STRING, length: 16)]
        public private(set) string $phone,
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $content,
        #[ORM\Column(type: Types::BOOLEAN)]
        public private(set) bool $isTest = false,
        ?string $reference = null,
    ) {
        $this->reference = $reference ?? Uuid::v7()->toRfc4122();
    }

    public function record(string $state, DateTimeImmutable $now): void
    {
        $this->gatewayState = $state;
        $this->state = match ($state) {
            'Sent' => SmsState::Sent,
            'Delivered' => SmsState::Delivered,
            'Failed' => SmsState::Failed,
            'Cancelled' => SmsState::Skipped,
            default => SmsState::Accepted,
        };
        $this->acceptedAt ??= $now;
        $this->checkedAt = $now;
        $this->nextAttemptAt = null;
        $this->error = null;
    }
}
