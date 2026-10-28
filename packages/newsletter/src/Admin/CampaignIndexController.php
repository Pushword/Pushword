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
        return $this->listing($request, false);
    }

    #[AdminRoute(path: '/newsletter/calendar', name: 'newsletter_campaign_calendar', options: ['methods' => ['GET']])]
    public function calendar(Request $request): Response
    {
        return $this->listing($request, true);
    }

    private function listing(Request $request, bool $calendar): Response
    {
        $index = $this->campaigns->collect();
        $source = $request->query->getString('source');
        $status = $request->query->getString('status');
        $host = $request->query->getString('host', $this->defaultHost);
        $audience = $request->query->getString('audience');
        $stage = $request->query->getString('stage');
        $query = trim($request->query->getString('q'));
        $sources = array_fill_keys(array_keys($index->notices), true);
        $statuses = [];
        $audiences = [];
        $hosts = '' === $host || '@unknown' === $host ? [] : [$host => true];
        foreach ($index->items as $item) {
            $sources[$item->source] = true;
            if (null !== $item->audience) {
                $audiences[$item->audience] = true;
            }

            foreach ($item->hosts as $itemHost) {
                $hosts[$itemHost] = true;
            }

            if (null !== $item->status) {
                $statuses[$item->status] = true;
            }
        }

        ksort($audiences);
        ksort($sources);
        ksort($statuses);
        ksort($hosts);
        $items = array_filter($index->items, static fn (CampaignIndexItem $item): bool => ('' === $source || $source === $item->source)
            && ('' === $status || $status === $item->status)
            && ('' === $stage || $stage === $item->editorialStage)
            && ('' === $audience || $audience === $item->audience)
            && match ($host) {
                '' => true,
                '@unknown' => [] === $item->hosts,
                default => \in_array($host, $item->hosts, true),
            }
            && ('' === $query || false !== mb_stripos($item->subject.' '.($item->audience ?? ''), $query)));
        uasort($items, static fn (CampaignIndexItem $first, CampaignIndexItem $second): int => (($second->sentAt ?? $second->scheduledAt)?->getTimestamp() ?? \PHP_INT_MIN)
            <=> (($first->sentAt ?? $first->scheduledAt)?->getTimestamp() ?? \PHP_INT_MIN)
            ?: strcmp($first->key, $second->key));

        return $this->render($calendar ? '@PushwordNewsletter/admin/campaign_calendar.html.twig' : '@PushwordNewsletter/admin/campaign_catalogue.html.twig', [
            'calendarView' => $calendar,
            'viewFilters' => ['host' => $host, 'source' => $source, 'status' => $status, 'stage' => $stage, 'audience' => $audience, 'q' => $query],
            'audiences' => array_keys($audiences),
            'audience' => $audience,
            'stage' => $stage,
            'newsletterSection' => 'campaign',
            'items' => $items,
            'notices' => $index->notices,
            'noticeLevels' => $index->noticeLevels,
            'availableCount' => \count($index->items),
            'hasFilters' => '' !== $audience || '' !== $stage || '' !== $source || '' !== $status || '' !== $query || '' !== $host,
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
