<?php

namespace Pushword\Core\Cache;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Adds traffic-driven maintenance to the persistent markdown cache.
 *
 * The marker check is cheap and runs at most once per process per maintenance
 * interval. The lock prevents a PHP-FPM fleet from scanning the same pool at
 * once. A maintenance failure is deliberately ignored: cache housekeeping must
 * never make a page fail to render.
 */
final class SelfCleaningCachePool implements AdapterInterface, PruneableInterface, ResettableInterface
{
    private const int MAINTENANCE_INTERVAL = 86400;

    private const string MAINTENANCE_VERSION = '1';

    private int $nextMaintenanceCheckAt = 0;

    public function __construct(
        private readonly AdapterInterface $pool,
        private readonly string $maintenanceDir,
    ) {
    }

    public function getItem(mixed $key): CacheItem
    {
        $this->maintain();

        return $this->pool->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        $this->maintain();

        return $this->pool->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        $this->maintain();

        return $this->pool->hasItem($key);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->pool->clear($prefix);
    }

    public function deleteItem(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->maintain();

        return $this->pool->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->maintain();

        return $this->pool->saveDeferred($item);
    }

    public function commit(): bool
    {
        $this->maintain();

        return $this->pool->commit();
    }

    public function prune(): bool
    {
        return $this->pool instanceof PruneableInterface && $this->pool->prune();
    }

    public function reset(): void
    {
        if ($this->pool instanceof ResetInterface) {
            $this->pool->reset();
        }
    }

    private function maintain(): void
    {
        $now = time();
        if ($now < $this->nextMaintenanceCheckAt) {
            return;
        }

        $this->nextMaintenanceCheckAt = $now + self::MAINTENANCE_INTERVAL;
        $marker = $this->maintenanceDir.'/markdown-maintenance';

        try {
            $freshUntil = $this->freshUntil($marker, $now);
            if (null !== $freshUntil) {
                $this->nextMaintenanceCheckAt = $freshUntil;

                return;
            }

            if (! is_dir($this->maintenanceDir) && ! @mkdir($this->maintenanceDir, 0777, true) && ! is_dir($this->maintenanceDir)) {
                return;
            }

            $lock = @fopen($this->maintenanceDir.'/markdown-maintenance.lock', 'c');
            if (false === $lock) {
                return;
            }

            try {
                if (! flock($lock, \LOCK_EX | \LOCK_NB)) {
                    return;
                }

                clearstatcache(true, $marker);
                $freshUntil = $this->freshUntil($marker, $now);
                if (null !== $freshUntil) {
                    $this->nextMaintenanceCheckAt = $freshUntil;

                    return;
                }

                if (self::MAINTENANCE_VERSION !== @file_get_contents($marker)) {
                    // Entries written before this decorator existed have no TTL,
                    // so prune() can never collect them. Drop them once.
                    $maintained = $this->pool->clear();
                } else {
                    $maintained = $this->prune();
                }

                if ($maintained) {
                    @file_put_contents($marker, self::MAINTENANCE_VERSION, \LOCK_EX);
                }

                $this->nextMaintenanceCheckAt = $now + self::MAINTENANCE_INTERVAL;
            } finally {
                @flock($lock, \LOCK_UN);
                @fclose($lock);
            }
        } catch (Throwable) {
            // Rendering must remain available if maintenance cannot run.
        }
    }

    private function freshUntil(string $marker, int $now): ?int
    {
        $modifiedAt = @filemtime($marker);
        if (false === $modifiedAt || self::MAINTENANCE_VERSION !== @file_get_contents($marker)) {
            return null;
        }

        $freshUntil = $modifiedAt + self::MAINTENANCE_INTERVAL;

        return $freshUntil > $now ? $freshUntil : null;
    }
}
