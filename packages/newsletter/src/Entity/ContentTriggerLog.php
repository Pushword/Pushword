<?php

namespace Pushword\Newsletter\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Repository\ContentTriggerLogRepository;

/**
 * "This trigger already handled this page." The unique (trigger, page) pair is
 * what keeps {@see \Pushword\Newsletter\Content\ContentTriggerRunner} stateless:
 * a missed tick only delays work, and a tick that runs twice writes nothing new.
 *
 * The page and the campaign are held as plain ids rather than as associations,
 * on purpose. This row has to outlive both — a marker that disappears with the
 * thing it marks is not a marker, and deleting a sent campaign would otherwise
 * let the same page be mailed a second time.
 */
#[ORM\Entity(repositoryClass: ContentTriggerLogRepository::class)]
#[ORM\Table(name: 'newsletter_content_trigger_log')]
#[ORM\UniqueConstraint(name: 'unique_newsletter_content_trigger_page', columns: ['trigger_id', 'page_id'])]
#[ORM\Index(name: 'idx_newsletter_content_trigger_campaign', columns: ['campaign_id'])]
class ContentTriggerLog implements IdInterface
{
    use IdTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ContentTrigger::class)]
        #[ORM\JoinColumn(name: 'trigger_id', nullable: false, onDelete: 'CASCADE')]
        private ContentTrigger $trigger,
        #[ORM\Column(name: 'page_id', type: Types::INTEGER)]
        private int $pageId,
        #[ORM\Column(name: 'campaign_id', type: Types::INTEGER)]
        private int $campaignId,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getTrigger(): ContentTrigger
    {
        return $this->trigger;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
