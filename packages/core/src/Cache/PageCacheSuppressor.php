<?php

namespace Pushword\Core\Cache;

/**
 * Request-scoped flag that disables page-cache invalidation for the duration of a bulk
 * operation (e.g. flat import). The static-generator entity listener short-circuits when
 * `isSuppressed()` returns true, so callers can batch a rebuild with `pw:cache:clear`
 * instead of firing per-page Messenger dispatches.
 *
 * Lives in core so bundles can depend on it without requiring static-generator to be installed.
 */
final class PageCacheSuppressor
{
    private int $depth = 0;

    public function isSuppressed(): bool
    {
        return $this->depth > 0;
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function suppress(callable $callback): mixed
    {
        ++$this->depth;

        try {
            return $callback();
        } finally {
            --$this->depth;
        }
    }
}
