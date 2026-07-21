<?php

namespace Pushword\Flat\Sync;

/**
 * A pluggable flat-file sync contributed by another package, discovered through
 * the `pushword.flat.sync` tag and driven by {@see \Pushword\Flat\FlatFileSync}
 * alongside the built-in Page/Media/Snippet syncs.
 *
 * Implement this to make a non-Page entity round-trip through `pw:flat:sync`
 * (import ↔ export ↔ delete-missing) without touching flat itself. The
 * `getEntityName()` string is what `pw:flat:sync --entity=<name>` targets.
 */
interface FlatSyncInterface
{
    /**
     * The `--entity` selector this sync answers to (e.g. "social-post").
     */
    public function getEntityName(): string;

    public function sync(?string $host = null, bool $forceExport = false): void;

    public function import(?string $host = null): void;

    public function export(?string $host = null): void;
}
