<?php

namespace Pushword\StaticGenerator\Cache\MessageHandler;

use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\Message\HostCacheRefreshMessage;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sweeps one cache-mode host incrementally after its render epoch moved.
 * Byte-identical renders skip their write, so a sweep costs CPU but never disk
 * churn for unaffected pages.
 */
#[AsMessageHandler]
final readonly class HostCacheRefreshHandler
{
    public function __construct(
        private SiteRegistry $apps,
        private RenderEpoch $renderEpoch,
        private StaticAppGenerator $staticAppGenerator,
    ) {
    }

    public function __invoke(HostCacheRefreshMessage $message): void
    {
        $app = $this->apps->findByHost($message->host);
        if (null === $app || ! StaticAppGenerator::isCacheMode($app)) {
            return;
        }

        $host = $app->getMainHost();

        // Debounce: coalesced bumps leave several messages behind, the first
        // completed sweep answers them all. sweptEpoch is the value sampled at
        // sweep start, so a bump landing mid-sweep never gets absorbed here.
        $stateManager = $this->staticAppGenerator->getStateManager();
        $stateManager->reload();
        if ($stateManager->getSweptEpoch($host) === $this->renderEpoch->get($host)) {
            return;
        }

        $this->staticAppGenerator->generate($host, incremental: true);
    }
}
