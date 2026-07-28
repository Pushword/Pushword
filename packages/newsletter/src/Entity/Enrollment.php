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
 * A contact's progress through one automation. `position` is the index of the
 * next step to send; the unique constraint is what keeps a contact from being
 * enrolled twice by successive ticks.
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'newsletter_enrollment')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_enrollment', columns: ['contact_id', 'automation_id'])]
#[ORM\Index(name: 'idx_newsletter_enrollment_due', columns: ['status', 'next_run_at'])]
class Enrollment implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: EnrollmentStatus::class)]
    private EnrollmentStatus $status = EnrollmentStatus::Active;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $enrolledAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Contact $contact,
        #[ORM\ManyToOne(targetEntity: Automation::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Automation $automation,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $nextRunAt,
    ) {
        $this->enrolledAt = new DateTimeImmutable();
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function getAutomation(): Automation
    {
        return $this->automation;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getStatus(): EnrollmentStatus
    {
        return $this->status;
    }

    public function getStatusLabel(): string
    {
        return $this->status->value;
    }

    public function isActive(): bool
    {
        return EnrollmentStatus::Active === $this->status;
    }

    public function getNextRunAt(): DateTimeImmutable
    {
        return $this->nextRunAt;
    }

    public function getEnrolledAt(): DateTimeImmutable
    {
        return $this->enrolledAt;
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
