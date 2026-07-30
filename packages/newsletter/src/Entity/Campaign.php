<?php

namespace Pushword\Newsletter\Entity;

use Cocur\Slugify\Slugify;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One broadcast. Authored as Markdown, sent to the whole subscribed audience or
 * to the subset matching {@see self::$segment}, immediately or at a scheduled
 * date. Sending is paced: recipients are materialised up-front and drained by
 * the tick, so a restart can never re-send.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: CampaignRepository::class)]
#[ORM\Table(name: 'newsletter_campaign')]
#[ORM\Index(name: 'idx_newsletter_campaign_status', columns: ['status'])]
class Campaign implements IdInterface, Stringable
{
    use IdTrait;
    use TimestampableTrait;

    /** The column holds 128; "YYMMDD-" takes 7 of them. */
    private const int SLUG_MAX_LENGTH = 120;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Audience::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Audience $audience = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject = '';

    /**
     * The campaign's analytics identity, carried as `utm_campaign`. Derived from
     * the subject when left empty, and prefixed with the send date when the
     * campaign is armed — so a year of campaigns reads in order in a report,
     * and rewording a subject afterwards renames nothing.
     */
    #[ORM\Column(type: Types::STRING, length: 128, options: ['default' => ''])]
    private string $slug = '';

    /** Short preview line most inboxes show after the subject. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $preheader = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyMarkdown = '';

    /**
     * Segment criteria; an empty list targets the whole subscribed audience.
     *
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $segment = [];

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CampaignStatus::class)]
    private CampaignStatus $status = CampaignStatus::Draft;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    /** Seconds between two mails; null falls back to the audience cadence. */
    #[Assert\Positive]
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $rateSeconds = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $recipientCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sentCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $failedCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $unsubCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $bounceCount = 0;

    private string $segmentJson = '';

    private bool $segmentJsonProvided = false;

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->subject ? $this->subject : 'Campaign #'.($this->id ?? '?');
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

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSlug(): string
    {
        return '' !== $this->slug ? $this->slug : $this->normalizeSlug($this->subject);
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $this->normalizeSlug((string) $slug);

        return $this;
    }

    /** Subjects are long and the column is not; leave room for the date prefix. */
    private function normalizeSlug(string $value): string
    {
        return rtrim(mb_substr(new Slugify()->slugify($value), 0, self::SLUG_MAX_LENGTH), '-');
    }

    /** Freeze the derived slug once, so later subject edits leave it alone. */
    #[ORM\PrePersist]
    public function initSlug(): void
    {
        $this->slug = $this->getSlug();
    }

    /**
     * The slug without its date prefix — what the author actually named the
     * campaign. Re-arming one therefore re-dates it instead of stacking
     * prefixes, and the real send date always wins over a typed-in one.
     */
    private function undatedSlug(): string
    {
        return preg_replace('/^\d{6}-/', '', $this->getSlug()) ?? $this->getSlug();
    }

    public function getPreheader(): ?string
    {
        return $this->preheader;
    }

    public function setPreheader(?string $preheader): self
    {
        $this->preheader = $preheader;

        return $this;
    }

    public function getBodyMarkdown(): string
    {
        return $this->bodyMarkdown;
    }

    public function setBodyMarkdown(?string $bodyMarkdown): self
    {
        $this->bodyMarkdown = (string) $bodyMarkdown;

        return $this;
    }

    /** @return array<mixed> */
    public function getSegment(): array
    {
        return $this->segment;
    }

    /** @param array<mixed> $segment */
    public function setSegment(array $segment): self
    {
        $this->segment = $segment;

        return $this;
    }

    /**
     * The admin's editing surface for the segment. Parsing happens during
     * validation so a malformed expression is a form error, not a 500.
     */
    public function getSegmentAsJson(): string
    {
        return $this->segmentJsonProvided ? $this->segmentJson : SegmentCriteria::toJson($this->segment);
    }

    public function setSegmentAsJson(?string $segmentJson): self
    {
        $this->segmentJson = (string) $segmentJson;
        $this->segmentJsonProvided = true;

        return $this;
    }

    #[Assert\Callback]
    public function validateSegment(ExecutionContextInterface $executionContext): void
    {
        if (! $this->segmentJsonProvided) {
            return;
        }

        try {
            $this->segment = SegmentCriteria::fromJson($this->segmentJson);
            $this->segmentJsonProvided = false;
        } catch (SegmentException $segmentException) {
            $executionContext->buildViolation($segmentException->getMessage())
                ->atPath('segmentAsJson')
                ->addViolation();
        }
    }

    public function getStatus(): CampaignStatus
    {
        return $this->status;
    }

    public function getStatusLabel(): string
    {
        return $this->status->value;
    }

    public function isDraft(): bool
    {
        return CampaignStatus::Draft === $this->status;
    }

    public function isScheduled(): bool
    {
        return CampaignStatus::Scheduled === $this->status;
    }

    public function isSending(): bool
    {
        return CampaignStatus::Sending === $this->status;
    }

    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /** Arm a draft: the tick materialises its recipients once the date has passed. */
    public function schedule(DateTimeImmutable $when): self
    {
        $this->status = CampaignStatus::Scheduled;
        $this->scheduledAt = $when;

        return $this;
    }

    /** Return a scheduled campaign to draft so it can be edited or re-armed. */
    public function revertToDraft(): self
    {
        $this->status = CampaignStatus::Draft;
        $this->scheduledAt = null;

        return $this;
    }

    /**
     * Arming is the moment the send date stops being a guess, so it is where the
     * analytics name gets its `YYMMDD` prefix — a campaign scheduled in March and
     * delayed to April is dated April, which is when people received it.
     */
    public function markSending(int $recipientCount): self
    {
        $this->status = CampaignStatus::Sending;
        $this->recipientCount = $recipientCount;
        $this->slug = new DateTimeImmutable()->format('ymd').'-'.$this->undatedSlug();

        return $this;
    }

    public function markSent(): self
    {
        $this->status = CampaignStatus::Sent;
        $this->sentAt = new DateTimeImmutable();

        return $this;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getRateSeconds(): ?int
    {
        return $this->rateSeconds;
    }

    public function setRateSeconds(?int $rateSeconds): self
    {
        $this->rateSeconds = null !== $rateSeconds ? max(1, $rateSeconds) : null;

        return $this;
    }

    public function getEffectiveRateSeconds(): int
    {
        return $this->rateSeconds ?? $this->audience?->getRateSeconds() ?? 30;
    }

    public function getRecipientCount(): int
    {
        return $this->recipientCount;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function incrementSent(): self
    {
        ++$this->sentCount;

        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function incrementFailed(): self
    {
        ++$this->failedCount;

        return $this;
    }

    public function getUnsubCount(): int
    {
        return $this->unsubCount;
    }

    public function incrementUnsub(): self
    {
        ++$this->unsubCount;

        return $this;
    }

    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    public function incrementBounce(): self
    {
        ++$this->bounceCount;

        return $this;
    }
}
