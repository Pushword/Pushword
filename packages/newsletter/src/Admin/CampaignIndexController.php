<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class CampaignIndexController extends AbstractController
{
    public function __construct(
        private readonly CampaignIndex $campaigns,
        private readonly EditorialTimezone $timezone,
        #[Autowire(param: 'pw.newsletter.catalogue_default_host')]
        private readonly string $defaultHost = '',
    ) {
    }

    #[AdminRoute(path: '/newsletter/campaigns', name: 'newsletter_campaign_catalogue', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        $index = $this->campaigns->collect();
        $source = $request->query->getString('source');
        $status = $request->query->getString('status');
        $host = $request->query->getString('host', $this->defaultHost);
        $query = trim($request->query->getString('q'));
        $sources = array_fill_keys(array_keys($index->notices), true);
        $statuses = [];
        $hosts = '' === $host || '@unknown' === $host ? [] : [$host => true];
        foreach ($index->items as $item) {
            $sources[$item->source] = true;
            foreach ($item->hosts as $itemHost) {
                $hosts[$itemHost] = true;
            }

            if (null !== $item->status) {
                $statuses[$item->status] = true;
            }
        }

        ksort($sources);
        ksort($statuses);
        ksort($hosts);
        $items = array_filter($index->items, static fn (CampaignIndexItem $item): bool => ('' === $source || $source === $item->source)
            && ('' === $status || $status === $item->status)
            && match ($host) {
                '' => true,
                '@unknown' => [] === $item->hosts,
                default => \in_array($host, $item->hosts, true),
            }
            && ('' === $query || false !== mb_stripos($item->subject.' '.($item->audience ?? ''), $query)));
        uasort($items, static fn (CampaignIndexItem $first, CampaignIndexItem $second): int => (($second->sentAt ?? $second->scheduledAt)?->getTimestamp() ?? \PHP_INT_MIN)
            <=> (($first->sentAt ?? $first->scheduledAt)?->getTimestamp() ?? \PHP_INT_MIN)
            ?: strcmp($first->key, $second->key));

        return $this->render('@PushwordNewsletter/admin/campaign_catalogue.html.twig', [
            'newsletterSection' => 'campaign',
            'items' => $items,
            'notices' => $index->notices,
            'noticeLevels' => $index->noticeLevels,
            'availableCount' => \count($index->items),
            'hasFilters' => '' !== $source || '' !== $status || '' !== $query || '' !== $host,
            'creationActions' => $index->creationActions,
            'timezone' => $this->timezone->timezone->getName(),
            'sources' => array_keys($sources),
            'statuses' => array_keys($statuses),
            'hosts' => array_keys($hosts),
            'host' => $host,
            'source' => $source,
            'status' => $status,
            'query' => $query,
        ]);
    }
}
