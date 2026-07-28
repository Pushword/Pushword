<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Entity\SharedTrait\ExtensiblePropertiesTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\Taggable;
use Pushword\Core\Entity\SharedTrait\TagsTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Repository\ContactRepository;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person in an audience, and the consent ledger for them: where they opted in,
 * from which IP, when they confirmed, when they left.
 *
 * `createdAt` is the registration date segments filter on; `customProperties`
 * holds whatever the site knows about them (`lastBoughtProduct`, …) and is
 * writable through the API; `tags` carries interests and manual segmentation in
 * one namespace.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'newsletter_contact')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_contact_email', columns: ['audience_id', 'email'])]
#[ORM\UniqueConstraint(name: 'unique_newsletter_contact_token', columns: ['token'])]
#[ORM\Index(name: 'idx_newsletter_contact_status', columns: ['audience_id', 'status'])]
class Contact implements IdInterface, Taggable, Stringable, CustomPropertiesInterface
{
    use ExtensiblePropertiesTrait;
    use IdTrait;
    use TagsTrait;
    use TimestampableTrait;

    #[Assert\NotBlank]
    #[Assert\Email(mode: 'strict')]
    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email = '';

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 8)]
    private string $locale = 'en';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContactStatus::class)]
    private ContactStatus $status = ContactStatus::Pending;

    /** Secret used by the confirm and unsubscribe links. Regenerated never — the links stay valid. */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $token;

    /** Where the subscription came from: a page slug, `api`, `import`, … */
    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    private ?string $source = null;

    /** The host that served the opt-in form. Provenance only — consent is scoped to the audience. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $optinHost = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $optinIp = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $bouncedAt = null;

    public function __construct(#[ORM\ManyToOne(targetEntity: Audience::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Audience $audience, string $email)
    {
        $this->setEmail($email);
        $this->token = bin2hex(random_bytes(32));
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name.' <'.$this->email.'>' : $this->email;
    }

    public function getAudience(): Audience
    {
        return $this->audience;
    }

    public function setAudience(Audience $audience): self
    {
        $this->audience = $audience;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $locale = trim((string) $locale);
        $this->locale = '' !== $locale ? $locale : 'en';

        return $this;
    }

    public function getStatus(): ContactStatus
    {
        return $this->status;
    }

    public function getStatusLabel(): string
    {
        return $this->status->value;
    }

    public function isSubscribed(): bool
    {
        return ContactStatus::Subscribed === $this->status;
    }

    public function isPending(): bool
    {
        return ContactStatus::Pending === $this->status;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = null !== $source ? mb_substr($source, 0, 120) : null;

        return $this;
    }

    public function getOptinHost(): ?string
    {
        return $this->optinHost;
    }

    public function setOptinHost(?string $optinHost): self
    {
        $this->optinHost = $optinHost;

        return $this;
    }

    public function getOptinIp(): ?string
    {
        return $this->optinIp;
    }

    public function setOptinIp(?string $optinIp): self
    {
        $this->optinIp = $optinIp;

        return $this;
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getUnsubscribedAt(): ?DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function getBouncedAt(): ?DateTimeImmutable
    {
        return $this->bouncedAt;
    }

    /**
     * Open a subscription: pending when the audience wants a confirmation,
     * immediately subscribed otherwise (an import of an already-consenting base).
     */
    public function optIn(bool $requireDoubleOptIn): self
    {
        $this->unsubscribedAt = null;
        $this->bouncedAt = null;

        if ($requireDoubleOptIn) {
            $this->status = ContactStatus::Pending;

            return $this;
        }

        $this->status = ContactStatus::Subscribed;
        $this->confirmedAt ??= new DateTimeImmutable();

        return $this;
    }

    /** Confirm a double opt-in. Anything but a pending contact is left untouched. */
    public function confirm(): self
    {
        if (ContactStatus::Pending !== $this->status) {
            return $this;
        }

        $this->status = ContactStatus::Subscribed;
        $this->confirmedAt = new DateTimeImmutable();

        return $this;
    }

    public function unsubscribe(): self
    {
        $this->status = ContactStatus::Unsubscribed;
        $this->unsubscribedAt = new DateTimeImmutable();

        return $this;
    }

    public function markBounced(): self
    {
        $this->status = ContactStatus::Bounced;
        $this->bouncedAt = new DateTimeImmutable();

        return $this;
    }

    public function getRegisteredAt(): DateTimeInterface
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }
}
