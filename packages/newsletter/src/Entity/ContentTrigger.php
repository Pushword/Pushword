<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Newsletter\Content\PageCriteria;
use Pushword\Newsletter\Repository\ContentTriggerRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * "When a page is published, mail the audience about it.".
 *
 * The counterpart of an {@see Automation}: an automation watches contacts and
 * drips a fixed sequence at each of them, this watches the site and broadcasts
 * one campaign per page. Both would fit badly in the other — a publication is
 * not a per-contact position in a sequence — so it is its own entity, and what
 * it produces is an ordinary scheduled {@see Campaign}.
 *
 * Two filters, in two languages: {@see self::$pageWhen} says which pages are
 * worth a mail, {@see self::$segment} says who receives it.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ContentTriggerRepository::class)]
#[ORM\Table(name: 'newsletter_content_trigger')]
class ContentTrigger implements IdInterface, Stringable
{
    use IdTrait;
    use TimestampableTrait;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Audience::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Audience $audience = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    /**
     * The Pushword hosts to watch. Not derived from the audience's own host: one
     * audience commonly spans several locale hosts, and which of them are worth
     * mailing about is an editorial choice.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $hosts = [];

    /**
     * Which published pages of those hosts deserve a mail; an empty list means
     * every one of them.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $pageWhen = [];

    /**
     * Who receives it, in the ordinary segment language; empty means the whole
     * subscribed audience.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $segment = [];

    /** How long after publication the mail goes out. 1440 is the day after. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1440])]
    private int $delayMinutes = 1440;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subjectTemplate = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyTemplate = '';

    /**
     * Pages published before this date never trigger anything. Set at creation,
     * like {@see Automation::$enrollFrom} and for the same reason: switching a
     * trigger on must not mail an entire back catalogue.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $triggerFrom;

    /** @var array<string, string> the admin's raw JSON, pending validation */
    private array $criteriaJson = [];

    public function __construct()
    {
        $this->triggerFrom = new DateTimeImmutable();
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : 'Content trigger #'.($this->id ?? '?');
    }

    public function getAudience(): ?Audience
    {
        return $this->audience;
    }

    public function setAudience(?Audience $audience): self
    {
        $this->audience = $audience;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /** @return string[] */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /** @param string[] $hosts */
    public function setHosts(array $hosts): self
    {
        $hosts = array_filter(array_map(trim(...), $hosts), static fn (string $host): bool => '' !== $host);
        $this->hosts = array_values(array_unique($hosts));

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getPageWhen(): array
    {
        return $this->pageWhen;
    }

    /** @param array<int, array<string, mixed>> $pageWhen */
    public function setPageWhen(array $pageWhen): self
    {
        $this->pageWhen = array_values($pageWhen);

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getSegment(): array
    {
        return $this->segment;
    }

    /** @param array<int, array<string, mixed>> $segment */
    public function setSegment(array $segment): self
    {
        $this->segment = array_values($segment);

        return $this;
    }

    /**
     * The admin's editing surface for both criteria lists. Parsing happens
     * during validation so a malformed expression is a form error, not a 500.
     */
    public function getPageWhenAsJson(): string
    {
        return $this->criteriaJson['pageWhen'] ?? PageCriteria::toJson($this->pageWhen);
    }

    public function setPageWhenAsJson(?string $json): self
    {
        $this->criteriaJson['pageWhen'] = (string) $json;

        return $this;
    }

    public function getSegmentAsJson(): string
    {
        return $this->criteriaJson['segment'] ?? SegmentCriteria::toJson($this->segment);
    }

    public function setSegmentAsJson(?string $json): self
    {
        $this->criteriaJson['segment'] = (string) $json;

        return $this;
    }

    #[Assert\Callback]
    public function validateCriteria(ExecutionContextInterface $executionContext): void
    {
        if (isset($this->criteriaJson['pageWhen'])) {
            try {
                $this->pageWhen = PageCriteria::fromJson($this->criteriaJson['pageWhen']);
                unset($this->criteriaJson['pageWhen']);
            } catch (SegmentException $segmentException) {
                $executionContext->buildViolation($segmentException->getMessage())
                    ->atPath('pageWhenAsJson')
                    ->addViolation();
            }
        }

        if (! isset($this->criteriaJson['segment'])) {
            return;
        }

        try {
            $this->segment = SegmentCriteria::fromJson($this->criteriaJson['segment']);
            unset($this->criteriaJson['segment']);
        } catch (SegmentException $segmentException) {
            $executionContext->buildViolation($segmentException->getMessage())
                ->atPath('segmentAsJson')
                ->addViolation();
        }
    }

    public function getDelayMinutes(): int
    {
        return $this->delayMinutes;
    }

    public function setDelayMinutes(int $delayMinutes): self
    {
        $this->delayMinutes = max(0, $delayMinutes);

        return $this;
    }

    public function getSubjectTemplate(): string
    {
        return $this->subjectTemplate;
    }

    public function setSubjectTemplate(string $subjectTemplate): self
    {
        $this->subjectTemplate = $subjectTemplate;

        return $this;
    }

    public function getBodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function setBodyTemplate(?string $bodyTemplate): self
    {
        $this->bodyTemplate = (string) $bodyTemplate;

        return $this;
    }

    public function getTriggerFrom(): DateTimeImmutable
    {
        return $this->triggerFrom;
    }

    public function setTriggerFrom(DateTimeImmutable $triggerFrom): self
    {
        $this->triggerFrom = $triggerFrom;

        return $this;
    }
}
