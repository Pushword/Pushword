<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Repository\TriggerLogRepository;

/**
 * "This automation already handled this subject." An automation's whole memory,
 * and the reason {@see \Pushword\Newsletter\Service\AutomationRunner} is
 * stateless: a missed tick only delays work, and a tick that runs twice writes
 * nothing new.
 *
 * The subject is held as a plain id rather than as an association, on purpose.
 * This row has to outlive what it marks — a marker that disappears with its
 * subject is not a marker — and the subject may not even be a Doctrine entity of
 * this application to begin with.
 *
 * One row per subject, never per mail: what it records is that the automation
 * reacted, not what the reaction produced. Which campaigns came out of it is on
 * the campaigns themselves ({@see Campaign::$triggerSubjectId}), so deleting a
 * sent campaign cannot make a page eligible for a second announcement.
 */
#[ORM\Entity(repositoryClass: TriggerLogRepository::class)]
#[ORM\Table(name: 'newsletter_trigger_log')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_trigger_subject', columns: ['automation_id', 'subject_id'])]
class TriggerLog implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Automation::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Automation $automation,
        #[ORM\Column(name: 'subject_id', type: Types::INTEGER)]
        public private(set) int $subjectId,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }
}
