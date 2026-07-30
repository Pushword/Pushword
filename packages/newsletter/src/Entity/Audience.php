<?php

namespace Pushword\Newsletter\Entity;

use Cocur\Slugify\Slugify;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Newsletter\Repository\AudienceRepository;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A mailing list, and the scope of consent: subscribing to one audience says
 * nothing about any other. One row per brand — a brand spread over several
 * locale hosts stays a single audience, so a person is never mailed twice.
 *
 * Also carries the sender identity every mail of the audience is sent with, and
 * the interest vocabulary the public subscribe endpoint is allowed to write.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AudienceRepository::class)]
#[ORM\Table(name: 'newsletter_audience')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_audience_slug', columns: ['slug'])]
class Audience implements IdInterface, Stringable
{
    use IdTrait;
    use TimestampableTrait;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', message: 'newsletter.audience.slug.invalid')]
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $slug = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    /**
     * The Pushword host this audience belongs to. Public links (confirm,
     * unsubscribe) are built from its `base_live_url`, so they keep working
     * when the site itself is statically generated.
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $mainHost = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $fromName = '';

    #[Assert\NotBlank]
    #[Assert\Email(mode: 'strict')]
    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $fromEmail = '';

    #[Assert\Email(mode: 'strict')]
    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $requireDoubleOptIn = true;

    /**
     * The only interest values the public subscribe endpoint may write. That
     * endpoint is public and cross-origin: without an allow-list, anyone could
     * author this audience's segmentation.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $interests = [];

    /** Seconds between two mails of this audience. Sets the ceiling the transport is asked to hold. */
    #[Assert\Positive]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 30])]
    private int $rateSeconds = 30;

    /**
     * The `utm_source` this audience's links carry. Null leaves them untouched:
     * the parameters are only worth adding where something reads them.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $utmSource = null;

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : $this->slug;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = trim(strtolower($slug));

        return $this;
    }

    public function getName(): string
    {
        return '' !== $this->name ? $this->name : $this->slug;
    }

    public function setName(?string $name): self
    {
        $this->name = (string) $name;

        return $this;
    }

    public function getMainHost(): string
    {
        return $this->mainHost;
    }

    public function setMainHost(string $mainHost): self
    {
        $this->mainHost = $mainHost;

        return $this;
    }

    public function getFromName(): string
    {
        return '' !== $this->fromName ? $this->fromName : $this->getName();
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = (string) $fromName;

        return $this;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): self
    {
        $this->fromEmail = mb_strtolower(trim($fromEmail));

        return $this;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function setReplyTo(?string $replyTo): self
    {
        $replyTo = null === $replyTo ? '' : mb_strtolower(trim($replyTo));
        $this->replyTo = '' !== $replyTo ? $replyTo : null;

        return $this;
    }

    public function requireDoubleOptIn(): bool
    {
        return $this->requireDoubleOptIn;
    }

    public function setRequireDoubleOptIn(bool $requireDoubleOptIn): self
    {
        $this->requireDoubleOptIn = $requireDoubleOptIn;

        return $this;
    }

    /** @return string[] */
    public function getInterests(): array
    {
        return $this->interests;
    }

    /** @param string[] $interests */
    public function setInterests(array $interests): self
    {
        $interests = array_filter(array_map(trim(...), $interests), static fn (string $i): bool => '' !== $i);
        $this->interests = array_values(array_unique($interests));

        return $this;
    }

    /**
     * Keep only the interests declared by this audience. Unknown values are
     * dropped silently: a prober learns nothing from a rejected tag.
     *
     * @param string[] $submitted
     *
     * @return string[]
     */
    public function filterInterests(array $submitted): array
    {
        return array_values(array_intersect(array_map(trim(...), $submitted), $this->interests));
    }

    public function getRateSeconds(): int
    {
        return $this->rateSeconds;
    }

    public function setRateSeconds(int $rateSeconds): self
    {
        $this->rateSeconds = max(1, $rateSeconds);

        return $this;
    }

    public function getUtmSource(): ?string
    {
        return $this->utmSource;
    }

    public function setUtmSource(?string $utmSource): self
    {
        $utmSource = new Slugify()->slugify((string) $utmSource);
        $this->utmSource = '' !== $utmSource ? $utmSource : null;

        return $this;
    }
}
