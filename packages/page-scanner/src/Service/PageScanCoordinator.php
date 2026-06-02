<?php

namespace Pushword\PageScanner\Service;

use DateInterval;
use Exception;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\LastTime;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Orchestrates page scans the same way for every front-end: it owns the
 * background dispatch, the per-host process locking, the shared output reading
 * and the ignore-filtering of cached results.
 *
 * Both the admin controller (HTML/HTMX) and the API controller (JSON) are thin
 * adapters over this service, so a scan started or polled through the API
 * behaves identically to one started from the admin.
 */
final readonly class PageScanCoordinator
{
    public const string PROCESS_TYPE = 'page-scanner';

    public const string COMMAND_PATTERN = 'pw:page-scan';

    /**
     * @param string[] $errorsToIgnore
     */
    public function __construct(
        private Filesystem $filesystem,
        private string $varDir,
        private string $pageScanInterval,
        private BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private BackgroundProcessManager $processManager,
        private ProcessOutputStorage $outputStorage,
        private SiteRegistry $siteRegistry,
        private array $errorsToIgnore = [],
    ) {
    }

    public function getProcessType(?string $host): string
    {
        return null === $host ? self::PROCESS_TYPE : self::PROCESS_TYPE.'--'.$host;
    }

    public function getFileCache(?string $host): string
    {
        $base = $this->varDir.'/page-scan';

        return null === $host ? $base : $base.'--'.$host;
    }

    /**
     * Cross-lock detection: a host scan is blocked while a global (all-hosts)
     * scan runs, and a global scan is blocked while any per-host scan runs.
     *
     * @return array{startTime: int|null, processType: string}|null
     */
    public function findBlockingProcess(?string $host): ?array
    {
        if (null !== $host) {
            return $this->checkProcessRunning(self::PROCESS_TYPE);
        }

        foreach ($this->siteRegistry->getHosts() as $h) {
            $result = $this->checkProcessRunning(self::PROCESS_TYPE.'--'.$h);
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

    public function shouldScan(?string $host, bool $force): bool
    {
        if ($force) {
            return true;
        }

        return ! new LastTime($this->getFileCache($host))->wasRunSince(new DateInterval($this->pageScanInterval));
    }

    /**
     * Dispatch a background scan, mirroring what the admin does: initialize the
     * shared output storage, dispatch, and start the interval clock. A dispatch
     * failure is surfaced through the output storage (status `error`) rather
     * than thrown, so the caller can keep polling and read the reason.
     */
    public function startScan(?string $host): void
    {
        $processType = $this->getProcessType($host);

        $this->outputStorage->clear($processType);
        $this->outputStorage->setStatus($processType, 'running');

        $commandParts = ['php', 'bin/console', 'pw:page-scan'];
        if (null !== $host) {
            $commandParts[] = $host;
        }

        try {
            $this->backgroundTaskDispatcher->dispatch($processType, $commandParts, self::COMMAND_PATTERN);
        } catch (Exception $exception) {
            $this->outputStorage->write($processType, 'Failed to start background process: '.$exception->getMessage()."\n");
            $this->outputStorage->setStatus($processType, 'error');
        }

        new LastTime($this->getFileCache($host))->setWasRun('now', false);
    }

    /**
     * @return array{status: string, output: string, isRunning: bool}
     */
    public function readOutput(string $processType): array
    {
        $isRunning = $this->getProcessInfo($processType)['isRunning'];
        $output = $this->outputStorage->read($processType)['content'];
        $storageStatus = $this->outputStorage->getStatus($processType);

        return [
            'status' => $isRunning ? 'running' : ($storageStatus ?? 'completed'),
            'output' => $output,
            'isRunning' => $isRunning,
        ];
    }

    /**
     * @return array{lastEdit: int, errorsByPages: array<int, array<int, array{page: array{host: string, slug: string}, message: string}>>}
     */
    public function readResults(?string $host): array
    {
        $fileCache = $this->getFileCache($host);

        if (! $this->filesystem->exists($fileCache)) {
            return ['lastEdit' => 0, 'errorsByPages' => []];
        }

        /** @var array<int, array<int, array{page: array{host: string, slug: string}, message: string}>> $errors */
        $errors = unserialize($this->filesystem->readFile($fileCache));

        return [
            'lastEdit' => (int) filemtime($fileCache),
            'errorsByPages' => $this->filterErrors($errors),
        ];
    }

    /**
     * @return array{startTime: int|null, processType: string}|null
     */
    private function checkProcessRunning(string $processType): ?array
    {
        $info = $this->getProcessInfo($processType);

        return $info['isRunning'] ? ['startTime' => $info['startTime'], 'processType' => $processType] : null;
    }

    /**
     * @param array<int, array<int, array{page: array{host: string, slug: string}, message: string}>> $errorsByPages
     *
     * @return array<int, array<int, array{page: array{host: string, slug: string}, message: string}>>
     */
    private function filterErrors(array $errorsByPages): array
    {
        if ([] === $this->errorsToIgnore) {
            return $errorsByPages;
        }

        $filtered = [];
        foreach ($errorsByPages as $pageId => $pageErrors) {
            $filteredPageErrors = [];
            foreach ($pageErrors as $error) {
                $route = $error['page']['host'].'/'.$error['page']['slug'];
                if (! $this->mustIgnoreError($route, $error['message'])) {
                    $filteredPageErrors[] = $error;
                }
            }

            if ([] !== $filteredPageErrors) {
                $filtered[$pageId] = $filteredPageErrors;
            }
        }

        return $filtered;
    }

    private function mustIgnoreError(string $route, string $message): bool
    {
        $plainMessage = strip_tags($message);

        foreach ($this->errorsToIgnore as $pattern) {
            if (str_contains($pattern, ': ')) {
                [$routePattern, $messagePattern] = explode(': ', $pattern, 2);
                if (fnmatch($routePattern, $route) && fnmatch($messagePattern, $plainMessage)) {
                    return true;
                }
            } elseif (fnmatch($pattern, $plainMessage)) {
                return true;
            }
        }

        return false;
    }
}
