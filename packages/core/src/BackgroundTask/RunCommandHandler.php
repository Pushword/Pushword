<?php

namespace Pushword\Core\BackgroundTask;

use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final readonly class RunCommandHandler
{
    public function __construct(
        private BackgroundProcessManager $processManager,
        private ProcessOutputStorage $outputStorage,
        private string $projectDir,
    ) {
    }

    public function __invoke(RunCommandMessage $message): void
    {
        $pidFile = $this->processManager->getPidFilePath($message->processType);

        $process = new Process($message->commandParts, $this->projectDir);
        $process->setTimeout(3600);

        try {
            $process->start();

            // The PID worth recording is the command's, not this consumer's. Liveness
            // is checked by matching the command pattern against /proc/<pid>/cmdline,
            // and a consumer's command line never names the command it runs — so
            // registering our own left the job reading as "not running", hence
            // "completed", for as long as its child took to boot.
            //
            // No PID means the command outran us to its own exit; there is no
            // liveness left to record, and it has already written its own outcome.
            $pid = $process->getPid();
            if (null !== $pid) {
                $this->processManager->registerProcess($pidFile, $message->commandPattern, $pid);
                $this->outputStorage->updateTrackedStatus($message->processType, 'running');
            }

            $process->wait();
        } finally {
            $this->processManager->unregisterProcess($pidFile);
        }
    }
}
