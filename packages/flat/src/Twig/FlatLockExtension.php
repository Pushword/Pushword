<?php

namespace Pushword\Flat\Twig;

use Pushword\Flat\Service\FlatLockManager;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig extension to expose flat lock status in templates.
 */
final readonly class FlatLockExtension
{
    public function __construct(
        private FlatLockManager $lockManager,
    ) {
    }

    /**
     * Get lock information for the given host.
     *
     * @return array{locked: bool, lockedAt: int, lockedBy: string, ttl: int, reason: string, lockedByUser?: string}|null
     */
    #[AsTwigFunction(name: 'flat_lock_info')]
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
    #[AsTwigFunction(name: 'is_webhook_locked')]
    public function isWebhookLocked(?string $host = null): bool
    {
        return $this->lockManager->isWebhookLocked($host);
    }

    /**
     * Check if the host has any active lock.
     */
    #[AsTwigFunction(name: 'is_flat_locked')]
    public function isLocked(?string $host = null): bool
    {
        return $this->lockManager->isLocked($host);
    }
}
