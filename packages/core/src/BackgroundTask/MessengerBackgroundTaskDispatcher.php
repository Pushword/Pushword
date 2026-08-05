<?php

namespace Pushword\Core\BackgroundTask;

use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerBackgroundTaskDispatcher implements BackgroundTaskDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private BackgroundProcessManager $processManager,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {
    }

    /** @param string[] $commandParts */
    public function dispatch(string $processType, array $commandParts, string $commandPattern): void
    {
        $pidFile = $this->processManager->getPidFilePath($processType);
        $this->processManager->cleanupStaleProcess($pidFile);

        $processInfo = $this->processManager->getProcessInfo($pidFile);
        if ($processInfo['isRunning']) {
            return;
        }

        $this->messageBus->dispatch(new RunCommandMessage(
            $processType,
            BackgroundCommand::pinEnvironment($commandParts, $this->environment),
            $commandPattern,
        ));
    }
}
