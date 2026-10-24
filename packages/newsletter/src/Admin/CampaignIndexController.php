<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class CampaignIndexController extends AbstractController
{
    public function __construct(
        private readonly CampaignIndex $campaigns,
        private readonly EditorialTimezone $timezone,
    ) {
    }

    #[AdminRoute(path: '/newsletter/campaigns', name: 'newsletter_campaign_catalogue', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        $index = $this->campaigns->collect();
        $source = $request->query->getString('source');
        $status = $request->query->getString('status');
        $query = trim($request->query->getString('q'));
        $sources = array_fill_keys(array_keys($index->notices), true);
        $statuses = [];
        foreach ($index->items as $item) {
            $sources[$item->source] = true;
            if (null !== $item->status) {
                $statuses[$item->status] = true;
            }
        }

        ksort($sources);
        ksort($statuses);
        $items = array_filter($index->items, static fn (CampaignIndexItem $item): bool => ('' === $source || $source === $item->source)
            && ('' === $status || $status === $item->status)
            && ('' === $query || false !== mb_stripos($item->subject.' '.($item->audience ?? ''), $query)));
        uasort($items, static fn (CampaignIndexItem $first, CampaignIndexItem $second): int => (($second->sentAt ?? $second->scheduledAt)?->getTimestamp() ?? \PHP_INT_MIN)
            <=> (($first->sentAt ?? $first->scheduledAt)?->getTimestamp() ?? \PHP_INT_MIN)
            ?: strcmp($first->key, $second->key));

        return $this->render('@PushwordNewsletter/admin/campaign_catalogue.html.twig', [
            'newsletterSection' => 'campaign',
            'items' => $items,
            'notices' => $index->notices,
            'creationActions' => $index->creationActions,
            'timezone' => $this->timezone->timezone->getName(),
            'sources' => array_keys($sources),
            'statuses' => array_keys($statuses),
            'source' => $source,
            'status' => $status,
            'query' => $query,
        ]);
    }
}
