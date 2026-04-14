<?php

namespace Pushword\Flat\Sync;

/**
 * Interface for conversation sync.
 * Implemented by the conversation package to allow optional integration.
 */
interface ConversationSyncInterface
{
    public function sync(?string $host = null, bool $forceExport = false): void;

    public function import(?string $host = null): void;

    public function export(?string $host = null): void;
}
