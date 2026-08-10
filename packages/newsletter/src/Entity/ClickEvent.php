<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Repository\ClickEventRepository;

/**
 * One click by one identified contact on one link of a mail — the personal data
 * click tracking exists to write, which is why a row only ever comes to exist
 * behind two consents: the audience's own switch and the contact's dated one.
 *
 * The mail is a campaign or an automation step, never both and never neither:
 * the two factories are the only doors in. Every relation cascades on delete,
 * and withdrawing the contact's consent purges their rows — a click is evidence
 * of reading behaviour, not a ledger the reporting may keep against their will.
 */
#[ORM\Entity(repositoryClass: ClickEventRepository::class)]
#[ORM\Table(name: 'newsletter_click_event')]
class ClickEvent implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $clickedAt;

    private function __construct(
        #[ORM\ManyToOne(targetEntity: Contact::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Contact $contact,
        /** The destination as the mail carried it, `utm_*` tags included. */
        #[ORM\Column(type: Types::TEXT)]
        public private(set) string $url,
        #[ORM\ManyToOne(targetEntity: Campaign::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        public private(set) ?Campaign $campaign = null,
        #[ORM\ManyToOne(targetEntity: Automation::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        public private(set) ?Automation $automation = null,
        /** The step within the automation, 0-based like {@see AutomationStep::$position}. */
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        public private(set) ?int $position = null,
    ) {
        $this->clickedAt = new DateTimeImmutable();
    }

    public static function onCampaign(Contact $contact, Campaign $campaign, string $url): self
    {
        return new self($contact, $url, $campaign);
    }

    public static function onStep(Contact $contact, Automation $automation, int $position, string $url): self
    {
        return new self($contact, $url, null, $automation, $position);
    }
}
