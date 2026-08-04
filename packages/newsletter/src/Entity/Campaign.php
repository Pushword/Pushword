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
 *
 * Everything the send writes about itself — the status, the counters, the
 * provenance — is `private(set)`: those are a record of what happened, and the
 * methods below are the only events that may change it.
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
    public ?Audience $audience = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $subject = '';

    /**
     * The campaign's analytics identity, carried as `utm_campaign`. Derived from
     * the subject when left empty, and prefixed with the send date when the
     * campaign is armed — so a year of campaigns reads in order in a report,
     * and rewording a subject afterwards renames nothing.
     */
    #[ORM\Column(type: Types::STRING, length: 128, options: ['default' => ''])]
    public string $slug = '' {
        get => '' !== $this->slug ? $this->slug : $this->normalizeSlug($this->subject);
        set(?string $value) => $this->normalizeSlug((string) $value);
    }

    /** Short preview line most inboxes show after the subject. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $preheader = null;

    #[ORM\Column(type: Types::TEXT)]
    public string $bodyMarkdown = '' {
        set(?string $value) => (string) $value;
    }

    /**
     * The same campaign in the other languages of its audience, keyed by locale:
     *
     *     {"de": {"subject": "…", "preheader": "…", "bodyMarkdown": "…"}}
     *
     * An audience spanning several locale hosts is one consent scope and one
     * list, so reaching it in every language it is read in is one campaign and
     * not one per language — which is also what keeps a bilingual reader from
     * being mailed twice.
     *
     * A JSON column rather than a `CampaignTranslation` entity: the values are
     * authored together, read together, never queried and never joined, so an
     * entity would buy a schema and a repository for nothing. It mirrors the way
     * `customProperties` already carries per-contact data on one aggregate.
     *
     * @var array<string, array<string, string>>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    public array $translations = [] {
        /** @param array<array-key, mixed> $value */
        set(array $value) => $this->normalizeTranslations($value);
    }

    /**
     * Segment criteria; an empty list targets the whole subscribed audience.
     *
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public array $segment = [];

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CampaignStatus::class)]
    public private(set) CampaignStatus $status = CampaignStatus::Draft;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?DateTimeImmutable $sentAt = null;

    /** Seconds between two mails; null falls back to the audience cadence. */
    #[Assert\Positive]
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $rateSeconds = null {
        set(?int $value) => null !== $value ? max(1, $value) : null;
    }

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public private(set) int $recipientCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public private(set) int $sentCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public private(set) int $failedCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public private(set) int $unsubCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    public private(set) int $bounceCount = 0;

    /**
     * Where this came from, when it was not written by hand. Provenance, and the
     * way back to the subject: during the delay the runner asks the automation's
     * source whether {@see self::$triggerSubjectId} still deserves its mail, and
     * drops the campaign when the answer is no.
     *
     * The automation may be deleted out from under it — a sent campaign is a
     * record and outlives the rule that produced it — so the link is nullable and
     * the id is kept beside it rather than through it.
     */
    #[ORM\ManyToOne(targetEntity: Automation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public private(set) ?Automation $automation = null;

    #[ORM\Column(name: 'trigger_subject_id', type: Types::INTEGER, nullable: true)]
    public private(set) ?int $triggerSubjectId = null;

    private string $segmentJson = '';

    private bool $segmentJsonProvided = false;

    private string $translationsJson = '';

    private bool $translationsJsonProvided = false;

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    /**
     * What one reader is sent, resolved at send time.
     *
     * Three steps: the locale as the contact carries it, then its language part
     * — `de-ch` reads the German written for eight languages over seventeen
     * hosts — then the campaign's own text. So a missing translation sends the
     * default rather than an empty mail, and a body is never frozen per
     * recipient: fixing a typo mid-campaign still reaches whoever is left.
     *
     * A translation may carry only some of the three fields; each falls back on
     * its own, so translating the subject without the preheader is allowed and
     * means what it looks like.
     *
     * @return array{subject: string, preheader: string|null, bodyMarkdown: string}
     */
    public function contentFor(string $locale): array
    {
        $translation = $this->translationFor($locale);

        return [
            'subject' => $translation['subject'] ?? $this->subject,
            'preheader' => $translation['preheader'] ?? $this->preheader,
            'bodyMarkdown' => $translation['bodyMarkdown'] ?? $this->bodyMarkdown,
        ];
    }

    /**
     * The languages this campaign is written in besides its own text.
     *
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        return array_keys($this->translations);
    }

    /**
     * @return array<string, string> the fields actually written for this locale,
     *                               empty when none is
     */
    private function translationFor(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        if ('' === $locale) {
            return [];
        }

        $language = explode('-', $locale)[0];

        return $this->translations[$locale] ?? $this->translations[$language] ?? [];
    }

    /**
     * Blank values are dropped rather than stored: an empty field in the admin
     * means "not translated", which is what makes it fall back. Stored the other
     * way, a locale opened and left alone would mail an empty body.
     *
     * @param array<array-key, mixed> $translations whatever the API or the admin textarea decoded
     *
     * @return array<string, array<string, string>>
     */
    private function normalizeTranslations(array $translations): array
    {
        $normalized = [];

        foreach ($translations as $locale => $fields) {
            $locale = $this->normalizeLocale((string) $locale);
            if ('' === $locale) {
                continue;
            }

            if (! \is_array($fields)) {
                continue;
            }

            $kept = [];
            foreach (['subject', 'preheader', 'bodyMarkdown'] as $field) {
                $value = \is_string($fields[$field] ?? null) ? trim($fields[$field]) : '';

                if ('' !== $value) {
                    $kept[$field] = $value;
                }
            }

            if ([] !== $kept) {
                $normalized[$locale] = $kept;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    private function normalizeLocale(string $locale): string
    {
        return str_replace('_', '-', mb_strtolower(trim($locale)));
    }

    public function __toString(): string
    {
        return '' !== $this->subject ? $this->subject : 'Campaign #'.($this->id ?? '?');
    }

    /** Subjects are long and the column is not; leave room for the date prefix. */
    private function normalizeSlug(string $value): string
    {
        return rtrim(mb_substr(new Slugify()->slugify($value), 0, self::SLUG_MAX_LENGTH), '-');
    }

    /**
     * Freeze the derived slug once, so later subject edits leave it alone.
     *
     * Reading goes through the get hook, which derives from the subject while
     * the column is still empty; normalising is idempotent and is what keeps
     * this from reading as the self-assignment a `$this->slug = $this->slug`
     * would be — and from being deleted as one.
     */
    #[ORM\PrePersist]
    public function initSlug(): void
    {
        $this->slug = $this->normalizeSlug($this->slug);
    }

    /**
     * The slug without its date prefix — what the author actually named the
     * campaign. Re-arming one therefore re-dates it instead of stacking
     * prefixes, and the real send date always wins over a typed-in one.
     */
    private function undatedSlug(): string
    {
        return preg_replace('/^\d{6}-/', '', $this->slug) ?? $this->slug;
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

    /**
     * The admin edits the translations as JSON, the way it edits the segment —
     * one textarea rather than a form grown at runtime for whichever languages
     * an audience turns out to be read in.
     */
    public function getTranslationsAsJson(): string
    {
        if ($this->translationsJsonProvided) {
            return $this->translationsJson;
        }

        return [] === $this->translations
            ? ''
            : json_encode($this->translations, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    public function setTranslationsAsJson(?string $translationsJson): self
    {
        $this->translationsJson = (string) $translationsJson;
        $this->translationsJsonProvided = true;

        return $this;
    }

    #[Assert\Callback]
    public function validateTranslations(ExecutionContextInterface $executionContext): void
    {
        if (! $this->translationsJsonProvided) {
            return;
        }

        $json = trim($this->translationsJson);

        if ('' === $json) {
            $this->translations = [];
            $this->translationsJsonProvided = false;

            return;
        }

        $decoded = json_decode($json, true);

        if (! \is_array($decoded)) {
            $executionContext->buildViolation('newsletter.campaign.translations.invalidJson')
                ->atPath('translationsAsJson')
                ->addViolation();

            return;
        }

        foreach ($decoded as $locale => $fields) {
            if (! \is_array($fields)) {
                $executionContext->buildViolation('newsletter.campaign.translations.invalidLocale')
                    ->setParameter('%locale%', (string) $locale)
                    ->atPath('translationsAsJson')
                    ->addViolation();

                return;
            }
        }

        /** @var array<string, array<string, string>> $decoded */
        $this->translations = $decoded;
        $this->translationsJsonProvided = false;
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

    /** Both or neither: a subject id without the automation that read it means nothing. */
    public function triggeredBy(Automation $automation, int $subjectId): self
    {
        $this->automation = $automation;
        $this->triggerSubjectId = $subjectId;

        return $this;
    }

    public function getEffectiveRateSeconds(): int
    {
        return $this->rateSeconds ?? $this->audience->rateSeconds ?? 30;
    }

    public function incrementSent(): self
    {
        ++$this->sentCount;

        return $this;
    }

    public function incrementFailed(): self
    {
        ++$this->failedCount;

        return $this;
    }

    public function incrementUnsub(): self
    {
        ++$this->unsubCount;

        return $this;
    }

    /** An opt-out taken back is not an opt-out this campaign's rate should carry. */
    public function decrementUnsub(): self
    {
        --$this->unsubCount;

        return $this;
    }

    public function incrementBounce(): self
    {
        ++$this->bounceCount;

        return $this;
    }
}
