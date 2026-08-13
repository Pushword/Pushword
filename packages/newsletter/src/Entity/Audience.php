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
use Symfony\Component\Validator\Constraints\Email;

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
    public string $slug = '' {
        set(string $value) => trim(strtolower($value));
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name = '' {
        get => '' !== $this->name ? $this->name : $this->slug;
        set(?string $value) => (string) $value;
    }

    /**
     * The Pushword host this audience belongs to. Public links (confirm,
     * unsubscribe) are built from its `base_live_url`, so they keep working
     * when the site itself is statically generated.
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $mainHost = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $fromName = '' {
        get => '' !== $this->fromName ? $this->fromName : $this->name;
        set(?string $value) => (string) $value;
    }

    #[Assert\NotBlank]
    #[Email(mode: Email::VALIDATION_MODE_STRICT)]
    #[ORM\Column(type: Types::STRING, length: 180)]
    public string $fromEmail = '' {
        set(string $value) => mb_strtolower(trim($value));
    }

    #[Email(mode: Email::VALIDATION_MODE_STRICT)]
    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    public ?string $replyTo = null {
        set(?string $value) {
            $replyTo = mb_strtolower(trim((string) $value));
            $this->replyTo = '' !== $replyTo ? $replyTo : null;
        }
    }

    /**
     * The sender's physical postal address, printed at the foot of every mail
     * this audience broadcasts.
     *
     * A commercial mail owes its reader a real-world address — CAN-SPAM
     * (15 U.S.C. 7704(a)(5)) makes it a condition of sending, and it is what
     * tells somebody who they actually heard from. It belongs to the audience
     * rather than to the site because the sender is the brand, and a brand
     * spans as many locale hosts as it likes. Left empty nothing is printed:
     * an audience that only carries transactional mail owes no address.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $postalAddress = null {
        set(?string $value) {
            $postalAddress = trim((string) $value);
            $this->postalAddress = '' !== $postalAddress ? $postalAddress : null;
        }
    }

    /**
     * Sends this audience's mail with no way off the list: no unsubscribe link
     * in either part of the body, and no `List-Unsubscribe` headers.
     *
     * Only for an audience that genuinely carries service messages — an order
     * confirmation, a booking reminder. A commercial mail owes its reader a way
     * out, and an inbox finding no `List-Unsubscribe` on bulk mail treats it as
     * unattributed, so switching this on for a newsletter costs deliverability
     * before it costs anything else. It pairs with {@see self::$postalAddress},
     * which the same kind of audience is the only one allowed to leave empty.
     *
     * A {@see Campaign} or an {@see Automation} may claim it for itself; neither
     * can take it back once it is set here.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $transactional = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $requireDoubleOptIn = true;

    /**
     * The only interest values the public subscribe endpoint may write. That
     * endpoint is public and cross-origin: without an allow-list, anyone could
     * author this audience's segmentation.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    public array $interests = [] {
        /** @param string[] $value */
        set(array $value) => array_values(array_unique(
            array_filter(array_map(trim(...), $value), static fn (string $i): bool => '' !== $i)
        ));
    }

    /** Seconds between two mails of this audience. Sets the ceiling the transport is asked to hold. */
    #[Assert\Positive]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 30])]
    public int $rateSeconds = 30 {
        set(int $value) => max(1, $value);
    }

    /**
     * The audience half of the click-tracking double gate, off by default.
     * Even switched on, a contact's links are only rewritten once that contact
     * carries their own {@see Contact::$clickTrackingConsentAt}.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $clickTracking = false;

    /**
     * The `utm_source` this audience's links carry. Null leaves them untouched:
     * the parameters are only worth adding where something reads them.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $utmSource = null {
        set(?string $value) {
            $utmSource = new Slugify()->slugify((string) $value);
            $this->utmSource = '' !== $utmSource ? $utmSource : null;
        }
    }

    public function __construct()
    {
        $this->initTimestampableProperties();
    }

    public function __toString(): string
    {
        return $this->name;
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
}
