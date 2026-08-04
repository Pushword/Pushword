<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Enum\RecipientState;
use Pushword\Newsletter\Repository\AutomationDeliveryRepository;

/**
 * One step of a drip as it actually went out — the automation's counterpart to
 * {@see CampaignRecipient}.
 *
 * A broadcast has a campaign to hold its ledger. A drip had none, so a step the
 * transport refused left nothing behind but a log line, and "did this contact
 * get step two?" had no answer: {@see Enrollment::$position} moves whether or
 * not the mail went out, deliberately, so that one permanent failure cannot
 * block a sequence forever.
 *
 * The rendered subject is kept rather than looked up. The step's own subject can
 * be edited afterwards, and the placeholders were this contact's — what the
 * reporting says went out has to be what went out.
 *
 * A row is born final. `Pending` and `Skipped` belong to a campaign's frozen
 * recipient list; a drip resolves its recipient at send time, so neither can
 * ever be true here.
 */
#[ORM\Entity(repositoryClass: AutomationDeliveryRepository::class)]
#[ORM\Table(name: 'newsletter_automation_delivery')]
#[ORM\Index(name: 'idx_newsletter_delivery_contact', columns: ['contact_id', 'state'])]
class AutomationDelivery implements IdInterface
{
    use IdTrait;

    /**
     * When the mail was handed to the transport, or when handing it over failed.
     * A failure has no send date and still has to be placeable in time — it is
     * the reason a step is missing from someone's sequence.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $attemptedAt;

    private function __construct(
        #[ORM\ManyToOne(targetEntity: Automation::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Automation $automation,
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Contact $contact,
        #[ORM\Column(type: Types::INTEGER)]
        public private(set) int $position,
        /** Which run of the sequence this belongs to — one per order, per booking. */
        #[ORM\Column(name: 'subject_id', type: Types::INTEGER)]
        public private(set) int $subjectId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        public private(set) string $subject,
        #[ORM\Column(type: Types::STRING, length: 20, enumType: RecipientState::class)]
        public private(set) RecipientState $state,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        public private(set) ?string $error = null,
    ) {
        $this->attemptedAt = new DateTimeImmutable();
    }

    /**
     * The step reached the transport.
     *
     * Both factories read the enrollment's `position`, so they are called before
     * it advances to the next step.
     */
    public static function sent(Enrollment $enrollment, string $subject): self
    {
        return new self(
            $enrollment->automation,
            $enrollment->contact,
            $enrollment->position,
            $enrollment->subjectId,
            mb_substr($subject, 0, 255),
            RecipientState::Sent,
        );
    }

    /** The transport refused it, and the reason is kept where it can be read. */
    public static function failed(Enrollment $enrollment, string $subject, string $error): self
    {
        return new self(
            $enrollment->automation,
            $enrollment->contact,
            $enrollment->position,
            $enrollment->subjectId,
            mb_substr($subject, 0, 255),
            RecipientState::Failed,
            mb_substr($error, 0, 255),
        );
    }

    /** Follow the row a merge kept — see {@see \Pushword\Newsletter\Service\ContactMerger}. */
    public function moveTo(Contact $contact): void
    {
        $this->contact = $contact;
    }

    /** A later bounce, credited here because this was the last mail sent. */
    public function markBounced(): self
    {
        $this->state = RecipientState::Bounced;

        return $this;
    }
}
