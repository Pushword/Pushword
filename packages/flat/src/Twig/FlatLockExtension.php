<?php

declare(strict_types=1);

namespace Pushword\Flat\Twig;

use Override;
use Pushword\Flat\Service\FlatLockManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension to expose flat lock status in templates.
 */
final class FlatLockExtension extends AbstractExtension
{
    public function __construct(
        private readonly FlatLockManager $lockManager,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('flat_lock_info', $this->getLockInfo(...)),
            new TwigFunction('is_webhook_locked', $this->isWebhookLocked(...)),
            new TwigFunction('is_flat_locked', $this->isLocked(...)),
        ];
    }

    /**
     * Get lock information for the given host.
     *
     * @return array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string, lockedByUser?: string}|null
     */
    public function getLockInfo(?string $host = null): ?array
    {
        if (! $this->lockManager->isLocked($host)) {
            return null;
        }

        return $this->lockManager->getLockInfo($host);
    }

    /**
     * Check if the host is locked by a webhook lock.
     */
    public function isWebhookLocked(?string $host = null): bool
    {
        return $this->lockManager->isWebhookLocked($host);
    }

    /**
     * Check if the host has any active lock.
     */
    public function isLocked(?string $host = null): bool
    {
        return $this->lockManager->isLocked($host);
    }
}
