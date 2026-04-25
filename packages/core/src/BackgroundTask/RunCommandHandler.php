<?php

namespace Pushword\Core\BackgroundTask;

use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class RunCommandHandler
{
    public function __construct(
        private BackgroundProcessManager $processManager,
        private string $projectDir,
    ) {
    }

    public function __invoke(RunCommandMessage $message): void
    {
        $pidFile = $this->processManager->getPidFilePath($message->processType);
        $this->processManager->registerProcess($pidFile, $message->commandPattern);

        try {
            $process = new Process($message->commandParts, $this->projectDir);
            $process->setTimeout(3600);
            $process->run();
        } finally {
            $this->processManager->unregisterProcess($pidFile);
        }
    }
}
