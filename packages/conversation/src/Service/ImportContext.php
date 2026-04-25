<?php

namespace Pushword\Conversation\Service;

/**
 * Service to track if we are currently importing conversations.
 * This allows to disable email notifications during imports.
 */
final class ImportContext
{
    private bool $isImporting = false;

    public function startImport(): void
    {
        $this->isImporting = true;
    }

    public function stopImport(): void
    {
        $this->isImporting = false;
    }

    public function isImporting(): bool
    {
        return $this->isImporting;
    }
}
