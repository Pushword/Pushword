<?php

namespace Pushword\Core\Tests\Perf;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR logger that counts DBAL "Executing statement" log messages.
 * Shared by the structural query-count guards in this directory.
 */
final class PerfQueryCounter extends AbstractLogger
{
    public int $count = 0;

    /** @var list<string> */
    public array $queries = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $msg = (string) $message;
        // Match both "Executing statement: {sql}" (from prepared-statement execute
        // and Connection::exec) and "Executing query: {sql}" (from Connection::query).
        if (! str_starts_with($msg, 'Executing statement') && ! str_starts_with($msg, 'Executing query')) {
            return;
        }

        ++$this->count;
        $sql = $context['sql'] ?? $msg;
        $this->queries[] = \is_string($sql) ? $sql : $msg;
    }
}
