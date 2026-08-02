<?php

namespace Pushword\StaticGenerator\Cache;

use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Event\RenderEpochBumpedEvent;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\Message\HostCacheRefreshMessage;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Turns epoch bumps into host sweep messages for cache-mode apps.
 *
 * Dispatch is deferred to terminate on purpose: a bump fires from Doctrine
 * lifecycle events, and on the sync transport the message would be handled
 * inline — a whole-host sweep inside an open flush (PagesGenerator clears the
 * EntityManager mid-run) and inside the admin response time. After terminate the
 * response is already sent, whatever the transport.
 *
 * A lost flush (process killed before terminate) loses only latency, never
 * correctness: the bump itself is recorded, and the next incremental generation
 * picks it out of the state-file comparison.
 */
final class HostSweepDispatcher implements ResetInterface
{
    /** @var array<string, true> */
    private array $pendingHosts = [];

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly MessageBusInterface $bus,
        private readonly PageCacheSuppressor $suppressor,
    ) {
    }

    #[AsEventListener]
    public function onEpochBumped(RenderEpochBumpedEvent $renderEpochBumpedEvent): void
    {
        // Suppressed = a bulk operation owns regeneration (the deploy chain runs
        // `pw:static --incremental` after a flat import). The bump stays recorded
        // either way; only the sweep is skipped.
        if ($this->suppressor->isSuppressed()) {
            return;
        }

        foreach ($renderEpochBumpedEvent->hosts as $host) {
            $app = $this->apps->findByHost($host);
            if (null === $app) {
                continue;
            }

            if (! StaticAppGenerator::isCacheMode($app)) {
                continue;
            }

            $this->pendingHosts[$app->getMainHost()] = true;
        }
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    public function flush(): void
    {
        if ([] === $this->pendingHosts) {
            return;
        }

        $hosts = array_keys($this->pendingHosts);
        $this->pendingHosts = [];

        foreach ($hosts as $host) {
            // The delay coalesces editing bursts on async transports; the sync
            // transport ignores it and handles inline (we are past the response).
            $this->bus->dispatch(new HostCacheRefreshMessage($host), [new DelayStamp(60_000)]);
        }
    }

    public function reset(): void
    {
        $this->pendingHosts = [];
    }
}
