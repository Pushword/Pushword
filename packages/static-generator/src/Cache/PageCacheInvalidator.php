<?php

namespace Pushword\StaticGenerator\Cache;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Component\EntityFilter\Filter\LinkCollector;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Two reactions to a Page write:
 *
 * - Refresh the page's own cached file (cache-mode hosts only, instant).
 * - Bump the host render epoch when the change can alter *other* pages' output
 *   (listings, navs, feeds, breadcrumbs). The bump feeds every consumer of the
 *   epoch: the sweep message for cache-mode hosts, cron `pw:static --incremental`
 *   for full-static ones — so it is deliberately not gated on cache mode.
 */
#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
final class PageCacheInvalidator implements ResetInterface
{
    /**
     * What cards, navs, sitemaps and feeds render of *other* pages
     * (see card.html.twig, rss.xml.twig, sitemap.xml.twig, breadcrumbs).
     */
    private const array LISTING_RELEVANT_FIELDS = [
        'title', 'h1', 'name', 'slug', 'parentPage', 'publishedAt', 'weight', 'locale', 'mainImage', 'host',
    ];

    /**
     * Relevance is decided in preUpdate (the changeset is gone by postUpdate) and
     * acted on in postUpdate (never act inside the flush). Worker mode: reset()
     * drains entries a failed flush would leave behind.
     *
     * @var array<int, true>
     */
    private array $pendingListingRelevant = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SiteRegistry $apps,
        private readonly PageCacheSuppressor $suppressor,
        private readonly PageCacheFileManager $fileManager,
        private readonly RenderEpoch $renderEpoch,
    ) {
    }

    public function postPersist(Page $page): void
    {
        $this->queueRefresh($page);

        // A new published page appears in listings, navs and feeds.
        if (! $this->suppressor->isSuppressed() && $page->isPublished()) {
            $this->renderEpoch->bump($page->host);
        }
    }

    public function preUpdate(Page $page, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        if ($this->isListingRelevant($page, $preUpdateEventArgs->getEntityChangeSet())) {
            $this->pendingListingRelevant[spl_object_id($page)] = true;
        }
    }

    public function postUpdate(Page $page): void
    {
        $this->queueRefresh($page);

        $relevant = isset($this->pendingListingRelevant[spl_object_id($page)]);
        unset($this->pendingListingRelevant[spl_object_id($page)]);

        if ($relevant && ! $this->suppressor->isSuppressed()) {
            $this->renderEpoch->bump($page->host);
        }
    }

    public function preRemove(Page $page): void
    {
        if ($this->suppressor->isSuppressed()) {
            return;
        }

        if ($this->isCacheSite($page)) {
            $this->fileManager->delete($page);
        }

        // Every listing that displayed the page must drop it.
        if ($page->isPublished()) {
            $this->renderEpoch->bump($page->host);
        }
    }

    public function reset(): void
    {
        $this->pendingListingRelevant = [];
    }

    /**
     * @param array<string, array{mixed, mixed}|PersistentCollection<array-key, object>> $changeSet
     */
    private function isListingRelevant(Page $page, array $changeSet): bool
    {
        // Draft edits touch no listing: drafts are not rendered anywhere else.
        // An appearing/disappearing publication date is the exception — that IS
        // the transition listings react to.
        if (! $page->isPublished() && ! isset($changeSet['publishedAt'])) {
            return false;
        }

        if ([] !== array_intersect(self::LISTING_RELEVANT_FIELDS, array_keys($changeSet))) {
            return true;
        }

        // A prose edit stays private to the page — unless it adds or removes an
        // internal link, which other pages render via linked_slugs & friends.
        if (isset($changeSet['mainContent']) && \is_array($changeSet['mainContent'])) {
            [$old, $new] = $changeSet['mainContent'];

            return LinkCollector::extractLinkSlugs(\is_string($old) ? $old : '')
                !== LinkCollector::extractLinkSlugs(\is_string($new) ? $new : '');
        }

        return false;
    }

    private function queueRefresh(Page $page): void
    {
        if ($this->suppressor->isSuppressed() || ! $this->isCacheSite($page) || null === $page->id) {
            return;
        }

        // Held pages keep their current static file; releasing the hold (set back
        // to null) is itself an update that re-enables the refresh.
        if (null !== $page->holdPublicationAt) {
            return;
        }

        $this->bus->dispatch(new PageCacheRefreshMessage($page->id));
    }

    private function isCacheSite(Page $page): bool
    {
        $app = $this->apps->findByHost($page->host);

        return null !== $app && StaticAppGenerator::isCacheMode($app);
    }
}
