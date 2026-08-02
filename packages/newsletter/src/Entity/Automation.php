<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Trigger\Source\ContactTriggerSource;
use Pushword\Newsletter\Trigger\TriggerSource;
use Pushword\Newsletter\Validator\ValidTriggerWhen;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Something happens, and a sequence of mails follows.
 *
 * What counts as "something happens" is a {@see TriggerSource}, named in
 * {@see self::$source}: contacts and pages ship with the bundle, a site adds
 * orders or bookings by tagging a service. {@see self::$triggerWhen} narrows it,
 * in whichever vocabulary that source speaks — so one screen, one grammar and
 * one set of steps cover "two mails after subscription" and "announce every
 * article the day after it goes out".
 *
 * The source also decides how each occurrence is delivered, per occurrence:
 *
 * - about one person — a new contact, a customer who ordered — and the steps are
 *   dripped at them, an {@see Enrollment} holding their place and
 *   {@see self::$stopWhen} re-checked before each one;
 * - about the site — a page was published — and each step becomes an ordinary
 *   scheduled {@see Campaign} broadcast to {@see self::$recipientWhen}, which is
 *   read at send time and so can name a state the mail is a reaction to: every
 *   subscriber who has not been seen in a month.
 *
 * A triggered mail is therefore paced, resumable and reportable exactly like a
 * hand-written one, and can be read, edited or cancelled in the admin during the
 * delay before it.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AutomationRepository::class)]
#[ORM\Table(name: 'newsletter_automation')]
#[ValidTriggerWhen]
class Automation implements IdInterface, Stringable
{
    use IdTrait;
    use TimestampableTrait;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Audience::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Audience $audience = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name = '';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $enabled = true;

    /**
     * The {@see TriggerSource} this watches. A name and not an enum: the point of
     * the registry is that a bundle adds one without this package knowing.
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 64, options: ['default' => ContactTriggerSource::NAME])]
    public string $source = ContactTriggerSource::NAME {
        set(string $value) => trim($value);
    }

    /**
     * Which of the source's subjects deserve the sequence, in that source's own
     * vocabulary; an empty list means every one of them.
     *
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public array $triggerWhen = [];

    /**
     * The Pushword hosts to watch, for the sources that are scoped to one. Not
     * derived from the audience's own host: one audience commonly spans several
     * locale hosts, and which of them are worth mailing about is an editorial
     * choice. Empty watches every one.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public array $hosts = [] {
        /** @param string[] $value */
        set(array $value) => array_values(array_unique(
            array_filter(array_map(trim(...), $value), static fn (string $host): bool => '' !== $host)
        ));
    }

    /**
     * Who receives an occurrence that is about the site rather than about one
     * person, in the ordinary segment language; empty means the whole subscribed
     * audience. Ignored when the occurrence names its own contact — a drip is
     * addressed to the person it was triggered by, and a second filter over them
     * would only be a way to silently drop them.
     *
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public array $recipientWhen = [];

    /**
     * Re-checked before each step of a drip: a match stops the enrollment. Empty
     * means the sequence always runs to its end.
     *
     * A broadcast has no equivalent and needs none: its recipients are resolved
     * when each step's campaign is armed, so someone who stopped matching
     * `recipientWhen` between step one and step two is simply not in step two.
     *
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public array $stopWhen = [];

    /**
     * Nothing that occurred before this date ever triggers anything. Set at
     * creation so switching on an automation with a wide rule cannot mail an
     * entire existing base, or announce an entire back catalogue, at once. A
     * column rather than a criterion, because this must not be possible to
     * forget.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $activeFrom;

    /** @var Collection<int, AutomationStep> */
    #[ORM\OneToMany(targetEntity: AutomationStep::class, mappedBy: 'automation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public private(set) Collection $steps;

    /** @var array<string, string> the admin's raw JSON, pending validation */
    private array $criteriaJson = [];

    public function __construct()
    {
        $this->activeFrom = new DateTimeImmutable();
        $this->steps = new ArrayCollection();
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : 'Automation #'.($this->id ?? '?');
    }

    /**
     * The admin's editing surface for the criteria lists. Parsing happens during
     * validation so a malformed expression is a form error, not a 500.
     *
     * `triggerWhen` is the odd one out: which vocabulary it is written in depends
     * on the source, which only the registry knows, so {@see ValidTriggerWhen}
     * parses it and this holds the raw text meanwhile.
     */
    public function getTriggerWhenAsJson(): string
    {
        return $this->criteriaJson['triggerWhen'] ?? SegmentCriteria::toJson($this->triggerWhen);
    }

    public function setTriggerWhenAsJson(?string $json): self
    {
        $this->criteriaJson['triggerWhen'] = (string) $json;

        return $this;
    }

    public function getRecipientWhenAsJson(): string
    {
        return $this->criteriaJson['recipientWhen'] ?? SegmentCriteria::toJson($this->recipientWhen);
    }

    public function setRecipientWhenAsJson(?string $json): self
    {
        $this->criteriaJson['recipientWhen'] = (string) $json;

        return $this;
    }

    public function getStopWhenAsJson(): string
    {
        return $this->criteriaJson['stopWhen'] ?? SegmentCriteria::toJson($this->stopWhen);
    }

    public function setStopWhenAsJson(?string $json): self
    {
        $this->criteriaJson['stopWhen'] = (string) $json;

        return $this;
    }

    /** The raw text a validator has yet to parse, or null once it has. */
    public function pendingCriteriaJson(string $key): ?string
    {
        return $this->criteriaJson[$key] ?? null;
    }

    public function criteriaJsonParsed(string $key): void
    {
        unset($this->criteriaJson[$key]);
    }

    /**
     * `recipientWhen` and `stopWhen` are contact criteria whatever the source is,
     * so they parse here. {@see ValidTriggerWhen} takes the third.
     */
    #[Assert\Callback]
    public function validateCriteria(ExecutionContextInterface $executionContext): void
    {
        foreach (['recipientWhen', 'stopWhen'] as $key) {
            $json = $this->pendingCriteriaJson($key);

            if (null === $json) {
                continue;
            }

            try {
                $parsed = SegmentCriteria::fromJson($json);

                if ('recipientWhen' === $key) {
                    $this->recipientWhen = $parsed;
                } else {
                    $this->stopWhen = $parsed;
                }

                $this->criteriaJsonParsed($key);
            } catch (SegmentException $segmentException) {
                $executionContext->buildViolation($segmentException->getMessage())
                    ->atPath($key.'AsJson')
                    ->addViolation();
            }
        }
    }

    /** @return list<AutomationStep> */
    public function getOrderedSteps(): array
    {
        $steps = $this->steps->toArray();
        usort($steps, static fn (AutomationStep $a, AutomationStep $b): int => $a->position <=> $b->position);

        return $steps;
    }

    public function getStep(int $position): ?AutomationStep
    {
        return $this->getOrderedSteps()[$position] ?? null;
    }

    public function countSteps(): int
    {
        return $this->steps->count();
    }

    /**
     * How long after the occurrence the step at `$position` goes out. A step's
     * own delay is counted from the one before it, so a broadcast — which
     * schedules every step up-front instead of advancing one at a time — has to
     * add them up.
     */
    public function delayToStep(int $position): int
    {
        $minutes = 0;

        foreach ($this->getOrderedSteps() as $index => $step) {
            if ($index > $position) {
                break;
            }

            $minutes += $step->delayMinutes;
        }

        return $minutes;
    }

    public function addStep(AutomationStep $step): self
    {
        if (! $this->steps->contains($step)) {
            $this->steps->add($step);
            $step->automation = $this;
        }

        return $this;
    }

    public function removeStep(AutomationStep $step): self
    {
        if ($this->steps->removeElement($step) && $step->automation === $this) {
            $step->automation = null;
        }

        return $this;
    }
}
