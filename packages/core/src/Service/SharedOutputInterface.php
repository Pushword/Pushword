<?php

namespace Pushword\Core\Service;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\Output;

/**
 * OutputInterface implementation that writes to ProcessOutputStorage.
 * Used to capture command output for sharing between CLI and web UI.
 */
final class SharedOutputInterface extends Output
{
    public function __construct(
        private readonly ProcessOutputStorage $storage,
        private readonly string $processType,
        int $verbosity = self::VERBOSITY_NORMAL,
        bool $decorated = false,
        ?OutputFormatterInterface $formatter = null,
    ) {
        parent::__construct($verbosity, $decorated, $formatter);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        // Strip ANSI escape codes for clean storage
        $cleanMessage = preg_replace('/\033\[[0-9;]*m/', '', $message) ?? $message;
        $this->storage->write($this->processType, $cleanMessage.($newline ? "\n" : ''));
    }
}
