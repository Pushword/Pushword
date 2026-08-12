<?php

namespace Pushword\Core\BackgroundTask;

use Psr\Log\LoggerInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessAlreadyRunningException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProcessBackgroundTaskDispatcher implements BackgroundTaskDispatcherInterface
{
    public function __construct(
        private BackgroundProcessManager $processManager,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
        private ?LoggerInterface $logger = null,
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
            // Skipping is the intended dedup — but a skipped warm-up leaves no other
            // trace, and derivatives silently missing weeks later all look alike, so
            // put it on the record. Same for the launch failure below (logged, then
            // propagated — the pinned contract). Note a successful launch still
            // guarantees nothing: the spawned process lives in the caller's cgroup
            // and dies with it on service restart or OOM, invisibly. Convergence is
            // the job of a periodic `pw:image:cache` run, not of this dispatcher.
            $this->logger?->info('Background task skipped, same task already running', [
                'processType' => $processType,
                'command' => implode(' ', $commandParts),
            ]);
        } catch (RuntimeException $runtimeException) {
            $this->logger?->error('Background task failed to launch', [
                'processType' => $processType,
                'command' => implode(' ', $commandParts),
                'error' => $runtimeException->getMessage(),
            ]);

            throw $runtimeException;
        }
    }
}
