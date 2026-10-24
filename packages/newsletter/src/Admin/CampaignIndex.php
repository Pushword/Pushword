<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use Pushword\Newsletter\Event\CampaignIndexItem;
use Pushword\Newsletter\Event\CollectCampaignIndex;
use Pushword\Newsletter\Repository\CampaignRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class CampaignIndex
{
    public function __construct(
        private CampaignRepository $campaigns,
        private UrlGeneratorInterface $urls,
        private EventDispatcherInterface $events,
    ) {
    }

    public function collect(): CollectCampaignIndex
    {
        $index = new CollectCampaignIndex();
        foreach ($this->campaigns->indexRows() as $campaign) {
            $index->add(new CampaignIndexItem(
                source: 'pushword',
                id: (string) $campaign['id'],
                subject: $campaign['subject'],
                status: $campaign['status']->value,
                scheduledAt: $campaign['scheduledAt'],
                sentAt: $campaign['sentAt'],
                actions: [
                    'newsletter.campaign.action.review' => $this->urls->generate('admin_newsletter_campaign_detail', ['entityId' => $campaign['id']]),
                    'newsletter.statistics.title' => $this->urls->generate('admin_newsletter_statistics_campaign', ['id' => $campaign['id']]),
                ],
                audience: $campaign['audience'],
            ));
        }

        $this->events->dispatch($index);
        CampaignIndexItem::validateActions($index->creationActions);
        foreach ($index->items as $item) {
            CampaignIndexItem::validateActions($item->actions);
        }

        return $index;
    }
}
