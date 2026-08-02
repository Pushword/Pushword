<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Enum\EnrollmentStatus;
use Pushword\Newsletter\Repository\EnrollmentRepository;

/**
 * A contact's progress through one automation, for the occurrence that started
 * it. `position` is the index of the next step to send; the unique constraint is
 * what keeps successive ticks from enrolling the same contact twice for the same
 * subject.
 *
 * The subject is part of that key rather than the contact alone: a source that
 * watches orders triggers the same automation at the same person once per order,
 * and each of those runs is its own sequence. A contact source names the contact
 * as its own subject, so there the constraint reads as it always did.
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'newsletter_enrollment')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_enrollment', columns: ['contact_id', 'automation_id', 'subject_id'])]
#[ORM\Index(name: 'idx_newsletter_enrollment_due', columns: ['status', 'next_run_at'])]
class Enrollment implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public private(set) int $position = 0;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: EnrollmentStatus::class)]
    public private(set) EnrollmentStatus $status = EnrollmentStatus::Active;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $enrolledAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Contact $contact,
        #[ORM\ManyToOne(targetEntity: Automation::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Automation $automation,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public private(set) DateTimeImmutable $nextRunAt,
        #[ORM\Column(name: 'subject_id', type: Types::INTEGER, options: ['default' => 0])]
        public private(set) int $subjectId = 0,
        /**
         * What the occurrence lent its templates, frozen here because the last
         * step goes out days after the thing it is about — and by then the order
         * may have shipped, or the page been retitled. A sequence has to read as
         * one conversation, so every step of it quotes the same values.
         *
         * @var array<string, string>
         */
        #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
        public private(set) array $placeholders = [],
    ) {
        $this->enrolledAt = new DateTimeImmutable();
    }

    public function getStatusLabel(): string
    {
        return $this->status->value;
    }

    public function isActive(): bool
    {
        return EnrollmentStatus::Active === $this->status;
    }

    /** Move to the next step, or finish when the sequence has no more. */
    public function advance(DateTimeImmutable $nextRunAt): self
    {
        ++$this->position;

        if ($this->position >= $this->automation->countSteps()) {
            return $this->finish();
        }

        $this->nextRunAt = $nextRunAt;

        return $this;
    }

    public function finish(): self
    {
        $this->status = EnrollmentStatus::Done;

        return $this;
    }

    public function stop(): self
    {
        $this->status = EnrollmentStatus::Stopped;

        return $this;
    }
}
