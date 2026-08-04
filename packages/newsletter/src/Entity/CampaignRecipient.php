<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Enum\RecipientState;
use Pushword\Newsletter\Repository\CampaignRecipientRepository;

/**
 * Per-contact send state for a campaign: the idempotency ledger. A row already
 * in `Sent` is never re-sent, so an interrupted drain cannot double-send when
 * the next tick picks it up.
 */
#[ORM\Entity(repositoryClass: CampaignRecipientRepository::class)]
#[ORM\Table(name: 'newsletter_campaign_recipient')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_recipient', columns: ['campaign_id', 'contact_id'])]
#[ORM\Index(name: 'idx_newsletter_recipient_state', columns: ['campaign_id', 'state'])]
class CampaignRecipient implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: RecipientState::class)]
    public private(set) RecipientState $state = RecipientState::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public private(set) ?string $error = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Campaign::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Campaign $campaign,
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Contact $contact,
    ) {
    }

    public function isPending(): bool
    {
        return RecipientState::Pending === $this->state;
    }

    /** Follow the row a merge kept — see {@see \Pushword\Newsletter\Service\ContactMerger}. */
    public function moveTo(Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function markSent(): self
    {
        $this->state = RecipientState::Sent;
        $this->sentAt = new DateTimeImmutable();
        $this->error = null;

        return $this;
    }

    /** The contact stopped being mailable after the campaign was armed. */
    public function markSkipped(): self
    {
        $this->state = RecipientState::Skipped;

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->state = RecipientState::Failed;
        $this->error = mb_substr($error, 0, 255);

        return $this;
    }

    public function markBounced(): self
    {
        $this->state = RecipientState::Bounced;

        return $this;
    }
}
