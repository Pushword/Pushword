<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Enum\ContactTransition;
use Pushword\Newsletter\Repository\ContactEventRepository;

/**
 * One act in a subscription's history, and the whole of what can be produced
 * when an opt-in is questioned.
 *
 * The dates on {@see Contact} answer *where things stand*: `confirmedAt` keeps
 * the first, `unsubscribedAt` and `bouncedAt` the last, and a new opt-in clears
 * those two. Somebody who subscribed, left, came back and left again leaves one
 * date behind — which is a state, not evidence. Article 7(1) GDPR asks the site
 * to demonstrate that consent was given; these rows are what demonstrates it.
 *
 * Append-only. Nothing here is ever updated or deleted, {@see moveTo()} apart —
 * a merge moves the history to the surviving row because it is the person's, not
 * the row's. It goes with the contact when the contact goes: an erasure that
 * left the ledger standing would keep the very data it was asked to remove.
 *
 * **Only the acts that give consent carry a host and an IP.** They are what a
 * disputed opt-in is answered with. An unsubscribe needs no such answer — nobody
 * ever asks a site to prove somebody left — so the two withdrawal factories take
 * no place to record one, and there is no door here to write it through.
 */
#[ORM\Entity(repositoryClass: ContactEventRepository::class)]
#[ORM\Table(name: 'newsletter_contact_event')]
class ContactEvent implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $occurredAt;

    private function __construct(
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Contact $contact,
        #[ORM\Column(type: Types::STRING, length: 20, enumType: ContactTransition::class)]
        public private(set) ContactTransition $transition,
        /** Who or what performed it: a page slug, `link`, `admin:<user>`, `api`, `mailbox`. */
        #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
        public private(set) ?string $source = null {
            set(?string $value) => null !== $value ? mb_substr($value, 0, 120) : null;
        },
        /** The host that served the form or the link — provenance, never a consent scope. */
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        public private(set) ?string $host = null,
        #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
        public private(set) ?string $ip = null,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    /** A subscription opened, or re-opened by somebody who had left. */
    public static function optIn(Contact $contact, ?string $source, ?string $host, ?string $ip): self
    {
        return new self($contact, ContactTransition::OptIn, $source, $host, $ip);
    }

    /**
     * The double opt-in answered.
     *
     * The host and the IP are the clicker's, so they are only ever passed by the
     * page the link lands on. An editor confirming from the admin gives their
     * own, which would be a record of the wrong person: the admin path names
     * itself in `source` and records neither.
     */
    public static function confirmed(Contact $contact, ?string $source, ?string $host, ?string $ip): self
    {
        return new self($contact, ContactTransition::Confirm, $source, $host, $ip);
    }

    /** An opt-out taken back. Consent given again, and evidenced as such. */
    public static function resubscribed(Contact $contact, ?string $source, ?string $host, ?string $ip): self
    {
        return new self($contact, ContactTransition::Resubscribe, $source, $host, $ip);
    }

    public static function unsubscribed(Contact $contact, ?string $source): self
    {
        return new self($contact, ContactTransition::Unsubscribe, $source);
    }

    /** A permanent delivery failure. The mail server's act, recorded with the person's. */
    public static function bounced(Contact $contact, ?string $source): self
    {
        return new self($contact, ContactTransition::Bounce, $source);
    }

    /**
     * Follow the person when two rows turn out to be one.
     *
     * The row keeps everything else it says: each one still names the moment,
     * the place and the provenance it was written with, so nothing is claimed
     * for the survivor that did not happen.
     */
    public function moveTo(Contact $contact): void
    {
        $this->contact = $contact;
    }
}
