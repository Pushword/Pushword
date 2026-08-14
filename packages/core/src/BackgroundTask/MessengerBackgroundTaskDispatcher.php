<?php

namespace Pushword\Core\BackgroundTask;

use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final readonly class MessengerBackgroundTaskDispatcher implements BackgroundTaskDispatcherInterface
{
    /** @param array<string, string> $backgroundTaskTransports */
    public function __construct(
        private MessageBusInterface $messageBus,
        private BackgroundProcessManager $processManager,
        private ProcessOutputStorage $outputStorage,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
        private array $backgroundTaskTransports,
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

        // Record the wait before handing the message over, never after: a worker
        // can pick it up — or a sync transport run it to completion — before
        // dispatch() returns, and a later write would put the queue back over a
        // job that has moved on.
        $this->outputStorage->updateTrackedStatus($processType, 'queued');

        $this->messageBus->dispatch(new RunCommandMessage(
            $processType,
            BackgroundCommand::pinEnvironment($commandParts, $this->environment),
            $commandPattern,
        ), $this->stampsFor($commandPattern));
    }

    /**
     * Messenger routes by message class and every background command shares one,
     * so a single transport serves them first-come: a site generation waits
     * behind however many thumbnails were queued before it. Naming a transport
     * for a command pattern gives that command its own lane.
     *
     * @return StampInterface[]
     */
    private function stampsFor(string $commandPattern): array
    {
        $transport = $this->backgroundTaskTransports[$commandPattern] ?? null;

        return null === $transport ? [] : [new TransportNamesStamp($transport)];
    }
}
