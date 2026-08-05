<?php

namespace Pushword\Core\BackgroundTask;

use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessAlreadyRunningException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProcessBackgroundTaskDispatcher implements BackgroundTaskDispatcherInterface
{
    public function __construct(
        private BackgroundProcessManager $processManager,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {
    }

    /** @param string[] $commandParts */
    public function dispatch(string $processType, array $commandParts, string $commandPattern): void
    {
        $pidFile = $this->processManager->getPidFilePath($processType);

        try {
            $this->processManager->startBackgroundProcess(
                $pidFile,
                BackgroundCommand::pinEnvironment($commandParts, $this->environment),
                $commandPattern,
            );
        } catch (ProcessAlreadyRunningException) {
            // Already running, skip silently
        }
    }
}
