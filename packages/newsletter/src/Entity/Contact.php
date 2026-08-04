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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person in an audience, and the consent ledger for them: where they opted in,
 * from which IP, when they confirmed, when they left.
 *
 * `createdAt` is the registration date segments filter on; `customProperties`
 * holds whatever the site knows about them (`lastBoughtProduct`, …) and is
 * writable through the API; `tags` carries interests and manual segmentation in
 * one namespace.
 *
 * A contact is keyed on an address or on a phone number — a booking taken over
 * the phone knows one and not the other. Consent and reachability are then two
 * questions rather than one: `subscribed` says they agreed to hear from the
 * site, {@see isMailable()} says a mail can carry it.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'newsletter_contact')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_contact_email', columns: ['audience_id', 'email'])]
#[ORM\UniqueConstraint(name: 'unique_newsletter_contact_phone', columns: ['audience_id', 'phone'])]
#[ORM\UniqueConstraint(name: 'unique_newsletter_contact_token', columns: ['token'])]
#[ORM\Index(name: 'idx_newsletter_contact_status', columns: ['audience_id', 'status'])]
// Every engine we run on allows many NULLs in a unique index, so the two
// constraints above hold for a base mixing both kinds without a partial index.
//
// Declared to the validator as well as to the schema: writing an identifier
// another row already holds has to come back as a violation naming the field,
// not as a driver exception thrown from the middle of a flush. `ignoreNull`
// keeps a base full of missing halves out of it.
#[UniqueEntity(fields: ['audience', 'email'], message: 'newsletter.contact.email.taken', errorPath: 'email', ignoreNull: true)]
#[UniqueEntity(fields: ['audience', 'phone'], message: 'newsletter.contact.phone.taken', errorPath: 'phone', ignoreNull: true)]
class Contact implements IdInterface, Taggable, Stringable, CustomPropertiesInterface
{
    use ExtensiblePropertiesTrait;
    use IdTrait;
    use TagsTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 180)]
    public string $name = '' {
        set(?string $value) => trim((string) $value);
    }

    /** Empty only until the first opt-in: the audience's host decides it, as a page's host decides a page's. */
    #[ORM\Column(type: Types::STRING, length: 8)]
    public string $locale = '' {
        set(?string $value) => trim((string) $value);
    }

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContactStatus::class)]
    public private(set) ContactStatus $status = ContactStatus::Pending;

    /** Secret used by the confirm and unsubscribe links. Regenerated never — the links stay valid. */
    #[ORM\Column(type: Types::STRING, length: 64)]
    public private(set) string $token;

    /** Where the subscription came from: a page slug, `api`, `import`, … */
    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    public ?string $source = null {
        set(?string $value) => null !== $value ? mb_substr($value, 0, 120) : null;
    }

    /** The host that served the opt-in form. Provenance only — consent is scoped to the audience. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $optinHost = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    public ?string $optinIp = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?DateTimeImmutable $bouncedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Audience::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Audience $audience,
        /** Null for somebody the site can only phone. An empty string is one of those, not an address. */
        #[Assert\Email(mode: 'strict')]
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        public ?string $email = null {
            set(?string $value) {
                $email = mb_strtolower(trim((string) $value));
                $this->email = '' !== $email ? $email : null;
            }
        },
        /**
         * Kept as digits and a leading `+`, which is what makes the unique index
         * mean anything: `06 12 34 56 78` and `0612345678` are one number, and a
         * base imported from a spreadsheet holds both spellings.
         *
         * No country is inferred. A local number stays local, so two people who
         * wrote theirs differently stay two rows — which is the honest answer when
         * nothing in the row says which country to assume.
         */
        #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
        public ?string $phone = null {
            set(?string $value) => self::normalizePhone($value);
        },
    ) {
        $this->token = bin2hex(random_bytes(32));
        $this->initTimestampableProperties();
    }

    /** Digits and a leading `+`; null for anything left with nothing to dial. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = (string) preg_replace('/(?!^\+)\D/', '', trim((string) $phone));

        return '' !== $digits && '+' !== $digits ? $digits : null;
    }

    /**
     * A contact is one or the other, never neither: a row keyed on nothing
     * cannot be found again, mailed, or asked to leave.
     */
    #[Assert\IsTrue(message: 'newsletter.contact.identifier.missing')]
    public function hasIdentifier(): bool
    {
        return null !== $this->email || null !== $this->phone;
    }

    /** What names this person wherever a screen has room for one line. */
    public function identifier(): string
    {
        return $this->email ?? $this->phone ?? '#'.($this->id ?? '?');
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name.' <'.$this->identifier().'>' : $this->identifier();
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

    /**
     * Whether a mail can be sent to this person right now — consent *and* an
     * address. Everything that sends asks this rather than {@see isSubscribed()},
     * which answers only the first half.
     */
    public function isMailable(): bool
    {
        return ContactStatus::Subscribed === $this->status && null !== $this->email;
    }

    /**
     * Open a subscription: pending when the audience wants a confirmation,
     * immediately subscribed otherwise (an import of an already-consenting base).
     *
     * A contact with no address is subscribed straight away whatever the
     * audience asks: pending means waiting for a click on a link sent by mail,
     * and there is no mail. The burden moves to `source`, which records who
     * entered the number — the same evidence a hand-made opt-in already owes.
     */
    public function optIn(bool $requireDoubleOptIn): self
    {
        $this->unsubscribedAt = null;
        $this->bouncedAt = null;

        if ($requireDoubleOptIn && null !== $this->email) {
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
