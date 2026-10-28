<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Pushword\Newsletter\Event\CollectCampaignIndex;
use Pushword\Newsletter\Repository\CampaignRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class CampaignIndex
{
    public function __construct(
        private CampaignRepository $campaigns,
        private UrlGeneratorInterface $urls,
        private EventDispatcherInterface $events,
        #[Autowire(param: 'pw.newsletter.catalogue_default_host')]
        private string $defaultHost = '',
    ) {
    }

    /** Use the catalogue when an application asks for a site filter or adds another source. */
    #[AsTwigFunction('newsletter_campaigns_route')]
    public function route(): string
    {
        return '' !== $this->defaultHost || $this->events->hasListeners(CollectCampaignIndex::class) ? 'admin_newsletter_campaign_catalogue' : 'admin_newsletter_campaign_index';
    }

    public function collect(): CollectCampaignIndex
    {
        $index = new CollectCampaignIndex();
        foreach ($this->campaigns->indexRows() as $campaign) {
            $actions = [
                'newsletter.campaign.action.review' => $this->urls->generate('admin_newsletter_campaign_detail', ['entityId' => $campaign['id']]),
                'newsletter.statistics.title' => $this->urls->generate('admin_newsletter_statistics_campaign', ['id' => $campaign['id']]),
            ];
            if (\in_array($campaign['status'], [CampaignStatus::Sending, CampaignStatus::Sent], true)) {
                $actions = array_reverse($actions);
            }

            $index->add(new CampaignIndexItem(
                source: 'pushword',
                id: (string) $campaign['id'],
                subject: '' !== trim($campaign['workingTitle']) ? $campaign['workingTitle'] : $campaign['subject'],
                status: $campaign['status']->value,
                scheduledAt: $campaign['scheduledAt'],
                sentAt: $campaign['sentAt'],
                actions: $actions,
                audience: $campaign['audience'],
                hosts: null === $campaign['mainHost'] || '' === $campaign['mainHost'] ? [] : [$campaign['mainHost']],
                editorialStage: $campaign['editorialStage']->value,
                version: null === $campaign['automationId'] ? $campaign['version'] : null,
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
