<?php

namespace Pushword\Core\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Event\RenderEpochBumpedEvent;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Per-host staleness token for rendered page output.
 *
 * Any change that can alter a page's HTML without touching the page row itself
 * (snippet, media, template, another page's metadata…) calls bump(). Generators
 * compare the epoch stored with each generated page against the current one:
 * a mismatch means "regenerate", whatever the cause.
 *
 * The token is opaque and random, never a counter: the storage is wiped by
 * `cache:clear`, and a reset counter would be indistinguishable from a current
 * one — the second wipe would freeze the cache forever. A random token can only
 * ever read as stale. get() persists on miss so every process converges on the
 * same value within a generation pass.
 *
 * Storage is a dedicated filesystem pool under kernel.cache_dir, NOT cache.app:
 * the epoch must be shared between the web process that records a change and
 * the CLI that sweeps it, and cache.app is commonly APCu — per-process, absent
 * on CLI — under which every process would mint its own token and incremental
 * generation would never converge (observed on a production fleet config).
 */
readonly class RenderEpoch
{
    private const string CACHE_KEY_PREFIX = 'pw.render_epoch.';

    private CacheItemPoolInterface $cache;

    public function __construct(
        #[Autowire(param: 'pw.render_epoch_dir')]
        string $renderEpochDir,
        private SiteRegistry $apps,
        private EventDispatcherInterface $eventDispatcher,
    ) {
        $this->cache = new FilesystemAdapter(namespace: '', defaultLifetime: 0, directory: $renderEpochDir);
    }

    public function get(string $host): string
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX.$this->resolveMainHost($host));
        $epoch = $item->get();
        if (\is_string($epoch) && '' !== $epoch) {
            return $epoch;
        }

        $epoch = $this->newToken();
        $item->set($epoch);
        $this->cache->save($item);

        return $epoch;
    }

    /**
     * @param ?string $host null bumps every configured app
     */
    public function bump(?string $host = null): void
    {
        $hosts = null === $host ? $this->allMainHosts() : [$this->resolveMainHost($host)];

        foreach ($hosts as $mainHost) {
            $item = $this->cache->getItem(self::CACHE_KEY_PREFIX.$mainHost);
            $item->set($this->newToken());
            $this->cache->save($item);
        }

        $this->eventDispatcher->dispatch(new RenderEpochBumpedEvent($hosts));
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function resolveMainHost(string $host): string
    {
        return $this->apps->findByHost($host)?->getMainHost() ?? $host;
    }

    /**
     * @return string[]
     */
    private function allMainHosts(): array
    {
        return array_values(array_unique(array_map(
            static fn (SiteConfig $siteConfig): string => $siteConfig->getMainHost(),
            array_values($this->apps->getAll()),
        )));
    }
}
