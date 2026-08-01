<?php

namespace Pushword\StaticGenerator;

use DateTimeImmutable;
use Exception;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;

/**
 * Orchestrates whole-site static generation the same way for every front-end: it
 * owns the background dispatch, the per-host process locking and the reading of
 * the shared console output.
 *
 * Both the admin controller (HTML/HTMX) and the API controller (JSON) are thin
 * adapters over this service, so a generation started or polled through the API
 * behaves identically to one started from the admin.
 *
 * Single-page regeneration is deliberately absent: it is cheap enough to run
 * in-process ({@see StaticAppGenerator::generatePage()}), so it never becomes a
 * background job to lock and poll.
 */
final readonly class StaticGenerationCoordinator
{
    public const string PROCESS_TYPE = 'static-generator';

    public const string COMMAND_PATTERN = 'pw:static';

    public function __construct(
        private BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private BackgroundProcessManager $processManager,
        private ProcessOutputStorage $outputStorage,
        private SiteRegistry $siteRegistry,
        private GenerationStateManager $stateManager,
    ) {
    }

    public function getProcessType(?string $host): string
    {
        return null === $host ? self::PROCESS_TYPE : self::PROCESS_TYPE.'--'.$host;
    }

    /**
     * Cross-lock detection: a host generation is blocked while a global (all-hosts)
     * one runs, and a global generation is blocked while any per-host one runs.
     *
     * @return array{startTime: int|null, processType: string}|null
     */
    public function findBlockingProcess(?string $host): ?array
    {
        if (null !== $host) {
            return $this->checkProcessRunning(self::PROCESS_TYPE);
        }

        foreach ($this->siteRegistry->getHosts() as $knownHost) {
            $result = $this->checkProcessRunning(self::PROCESS_TYPE.'--'.$knownHost);
            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{isRunning: bool, startTime: int|null, pid: int|null}
     */
    public function getProcessInfo(string $processType): array
    {
        $pidFile = $this->processManager->getPidFilePath($processType);
        $this->processManager->cleanupStaleProcess($pidFile);

        return $this->processManager->getProcessInfo($pidFile);
    }

    /**
     * Dispatch a background generation: initialize the shared output storage, then
     * dispatch. A dispatch failure is surfaced through the output storage (status
     * `error`) rather than thrown, so the caller can keep polling and read why.
     */
    public function startGeneration(?string $host, bool $incremental = false): void
    {
        $processType = $this->getProcessType($host);

        $this->outputStorage->clear($processType);
        $this->outputStorage->setStatus($processType, 'running');

        $commandParts = ['php', 'bin/console', 'pw:static'];
        if (null !== $host) {
            $commandParts[] = $host;
        }

        if ($incremental) {
            $commandParts[] = '--incremental';
        }

        // Both front-ends read this output as text; never let an inherited agent
        // environment switch the command to its one-line JSON format.
        $commandParts[] = '--format=text';

        try {
            $this->backgroundTaskDispatcher->dispatch($processType, $commandParts, self::COMMAND_PATTERN);
        } catch (Exception $exception) {
            $this->outputStorage->write($processType, 'Failed to start background process: '.$exception->getMessage()."\n");
            $this->outputStorage->setStatus($processType, 'error');
        }
    }

    /**
     * @return array{status: string, output: string, isRunning: bool, errors: list<string>}
     */
    public function readOutput(string $processType): array
    {
        $isRunning = $this->getProcessInfo($processType)['isRunning'];
        $output = $this->outputStorage->read($processType)['content'];
        $errors = $this->parseErrors($output);
        $storageStatus = $this->outputStorage->getStatus($processType);

        $status = match (true) {
            $isRunning => 'running',
            'error' === $storageStatus || [] !== $errors => 'error',
            default => 'completed',
        };

        return ['status' => $status, 'output' => $output, 'isRunning' => $isRunning, 'errors' => $errors];
    }

    /**
     * When the exported output of a scope was last rebuilt in full. For the
     * all-hosts scope this is the *oldest* of the per-host timestamps — the age
     * the whole export can be trusted to — and null as soon as one host has
     * never been generated.
     */
    public function getLastGenerationTime(?string $host): ?DateTimeImmutable
    {
        if (null !== $host) {
            return $this->stateManager->getLastGenerationTime($host);
        }

        $oldest = null;
        foreach ($this->siteRegistry->getHosts() as $knownHost) {
            $time = $this->stateManager->getLastGenerationTime($knownHost);
            if (null === $time) {
                return null;
            }

            if (null === $oldest || $time < $oldest) {
                $oldest = $time;
            }
        }

        return $oldest;
    }

    /**
     * The console output carries no machine-readable error channel, so errors are
     * recognized by their wording — same heuristic the admin has always used.
     *
     * @return list<string>
     */
    public function parseErrors(string $output): array
    {
        $errors = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            $lowerLine = strtolower($line);
            if (str_contains($lowerLine, 'error')
                || str_contains($lowerLine, 'failed')
                || str_contains($lowerLine, 'exception')
            ) {
                $errors[] = $line;
            }
        }

        return $errors;
    }

    /**
     * @return array{startTime: int|null, processType: string}|null
     */
    private function checkProcessRunning(string $processType): ?array
    {
        $info = $this->getProcessInfo($processType);

        return $info['isRunning'] ? ['startTime' => $info['startTime'], 'processType' => $processType] : null;
    }
}
