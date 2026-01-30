<?php

namespace Pushword\Core\BackgroundTask;

use Pushword\Core\Service\BackgroundProcessManager;
use RuntimeException;

final readonly class ProcessBackgroundTaskDispatcher implements BackgroundTaskDispatcherInterface
{
    public function __construct(
        private BackgroundProcessManager $processManager,
    ) {
    }

    /** @param string[] $commandParts */
    public function dispatch(string $processType, array $commandParts, string $commandPattern): void
    {
        $pidFile = $this->processManager->getPidFilePath($processType);

        try {
            $this->processManager->startBackgroundProcess($pidFile, $commandParts, $commandPattern);
        } catch (RuntimeException) {
            // Already running, skip silently
        }
    }
}
