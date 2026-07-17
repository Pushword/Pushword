<?php

namespace Pushword\PageScanner\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Service\SharedOutputInterface;
use Pushword\Core\Service\TeeOutput;
use Pushword\PageScanner\Controller\PageScannerController;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ParallelUrlChecker;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @phpstan-type ScanError array{message: string, page: array{id: int, slug: string, h1: string, metaRobots: string, host: string}}
 */
#[AsCommand(
    name: 'pw:page-scan',
    description: 'Find dead links, 404, 301 and more in your content.'
)]
final class PageScannerCommand
{
    use AgentOutputTrait;

    private const string PROCESS_TYPE = 'page-scanner';

    private const string COMMAND_PATTERN = 'pw:page-scan';

    /**
     * @param string[] $errorsToIgnore
     */
    public function __construct(
        private readonly PageScannerService $scanner,
        private readonly Filesystem $filesystem,
        private readonly PageRepository $pageRepo,
        private readonly ParallelUrlChecker $parallelUrlChecker,
        private readonly BackgroundProcessManager $processManager,
        private readonly ProcessOutputStorage $outputStorage,
        private readonly LinkGraphStorage $linkGraphStorage,
        private readonly array $errorsToIgnore,
        string $varDir,
    ) {
        PageScannerController::setFileCache($varDir);
    }

    /**
     * @return array<int, array<ScanError>>
     */
    private function scanAll(string $host): array
    {
        $this->stopwatch?->start('preload.caches');
        $this->scanner->preloadCaches();
        $this->stopwatch?->stop('preload.caches');

        // Edges accumulate across the loop below, so start from a clean slate.
        $this->scanner->linkGraphScanner->reset();
        $this->scannedNodes = [];
        $this->scanCompleted = true;

        $pages = $this->pageRepo->getPublishedPages($host);
        $pagesCount = \count($pages);

        // Single pass: scan all pages, collect internal errors AND external URLs
        $this->human(\sprintf('Scanning %d pages...', $pagesCount));
        $this->scanner->linkedDocsScanner->enableDeferredExternalMode();

        $errors = [];
        $errorNbr = 0;
        $currentPage = 0;
        $lastLineWasError = false;
        $maxErrors = $this->limit > 0 ? $this->limit : 500;

        foreach ($pages as $page) {
            ++$currentPage;
            $pageSlug = $page->getSlug() ?: 'index';
            $pageHost = $page->host ?? '';

            // Progress indicator: overwrite line unless previous was an error
            if (! $this->agentMode) {
                if ($lastLineWasError) {
                    $this->output?->write(\sprintf('Scanning... [%d/%d]', $currentPage, $pagesCount));
                } else {
                    $this->output?->write(\sprintf("\rScanning... [%d/%d]", $currentPage, $pagesCount));
                }
            }

            $lastLineWasError = false;

            // A redirection is a 301, not a page: it renders no HTML, so it would
            // enter the graph as a node with no outbound link and read as an orphan.
            if (! $page->hasRedirection()) {
                $this->scannedNodes[] = $pageHost.'/'.$page->getSlug();
            }

            $this->stopwatch?->start('scanPage');
            $this->stopwatch?->start('scan:'.$page->getSlug());
            $scan = $this->scanner->scan($page);
            $event = $this->stopwatch?->stop('scan:'.$page->getSlug());
            $this->stopwatch?->stop('scanPage');

            if (! $this->agentMode && null !== $event && $event->getDuration() > 500 && null !== $this->output && $this->output->isVerbose()) {
                $this->output->writeln("\n".\sprintf('<comment>⏱ %s/%s: %dms (slow)</comment>', $pageHost, $pageSlug, $event->getDuration()));
                $lastLineWasError = true;
            }

            if (true !== $scan) {
                $pageId = (int) $page->id;
                $errors[$pageId] = $scan;

                if (! $this->agentMode) {
                    $hasVisibleErrors = false;
                    foreach ($scan as $s) {
                        $route = $s['page']['host'].'/'.$s['page']['slug'];
                        if (! $this->mustIgnoreError($route, $s['message'])) {
                            if (! $hasVisibleErrors) {
                                $this->output?->writeln("\n".$pageHost.'/'.$pageSlug);
                                $hasVisibleErrors = true;
                            }

                            $this->output?->writeln('  <error>➜ '.$this->formatErrorForCli($s['message']).'</error>');
                        }
                    }

                    if ($hasVisibleErrors) {
                        $lastLineWasError = true;
                    }
                }

                $errorNbr += \count($errors[$pageId]);
            }

            if ($errorNbr > $maxErrors) {
                $this->human("\n".\sprintf('Too many errors (>%d), stopping scan...', $maxErrors));
                $this->scanCompleted = false;

                break;
            }
        }

        $this->pagesScanned = $currentPage;

        if (! $this->agentMode && ! $lastLineWasError) {
            $this->output?->writeln('');
        }

        // Parallel external URL validation
        if (! $this->skipExternal) {
            $externalUrls = $this->scanner->linkedDocsScanner->getCollectedExternalUrls();
            $urlCount = \count($externalUrls);
            if ($urlCount > 0) {
                $this->human(\sprintf('Checking %d external URLs in parallel...', $urlCount));
                $this->stopwatch?->start('external.urls');
                $urlResults = $this->parallelUrlChecker->checkUrls($externalUrls);
                $this->stopwatch?->stop('external.urls');
                $this->scanner->linkedDocsScanner->setExternalUrlResults($urlResults);
            }
        }

        // Resolve deferred external URL errors
        $externalErrors = $this->skipExternal ? [] : $this->scanner->linkedDocsScanner->resolveDeferredExternalErrors();
        foreach ($externalErrors as $pageId => $pageErrors) {
            foreach ($pageErrors as $error) {
                $route = $error['page']['host'].'/'.$error['page']['slug'];
                if (! $this->agentMode && ! $this->mustIgnoreError($route, $error['message'])) {
                    $this->output?->writeln($route.' <error>➜ '.$this->formatErrorForCli($error['message']).'</error>');
                }
            }

            $errors[$pageId] = [...($errors[$pageId] ?? []), ...$pageErrors];
        }

        $this->scanner->linkedDocsScanner->disableDeferredExternalMode();

        return $errors;
    }

    private ?OutputInterface $output = null;

    private bool $skipExternal = false;

    private int $limit = 0;

    private bool $agentMode = false;

    private int $pagesScanned = 0;

    /** @var list<string> `host/slug` of every page actually rendered, the link graph's nodes. */
    private array $scannedNodes = [];

    /** False when the error limit aborted the loop: the graph would then be missing edges. */
    private bool $scanCompleted = true;

    private ?Stopwatch $stopwatch = null;

    /**
     * Write a human-facing line, suppressed when emitting agent-optimized output.
     */
    private function human(string $message): void
    {
        if (! $this->agentMode) {
            $this->output?->writeln($message);
        }
    }

    /**
     * Emit a single compact JSON document for AI agents: pages grouped, ignored
     * errors filtered out, no ANSI/progress noise. Inspired by laravel/pao.
     *
     * @param array<int, array<ScanError>> $errors
     */
    private function printAgentSummary(OutputInterface $output, array $errors, int $durationMs): void
    {
        $issues = [];
        $errorCount = 0;

        foreach ($errors as $pageErrors) {
            $route = '';
            $messages = [];
            foreach ($pageErrors as $error) {
                $route = $error['page']['host'].'/'.$error['page']['slug'];
                if ($this->mustIgnoreError($route, $error['message'])) {
                    continue;
                }

                $messages[] = $this->formatErrorForCli($error['message']);
            }

            if ([] !== $messages) {
                $issues[] = ['page' => $route, 'errors' => $messages];
                $errorCount += \count($messages);
            }
        }

        $summary = [
            'tool' => 'pw:page-scan',
            'result' => [] === $issues ? 'passed' : 'failed',
            'pages_scanned' => $this->pagesScanned,
            'pages_with_errors' => \count($issues),
            'errors' => $errorCount,
            'issues' => $issues,
            'duration_ms' => $durationMs,
        ];

        $this->writeAgentJson($output, $summary);
    }

    private function formatErrorForCli(string $message): string
    {
        // Replace HTML formatting with CLI-friendly equivalents
        $message = str_replace(['<code>', '</code>'], '`', $message);
        $message = str_replace('<br>', ' ', $message);

        // Extract content from textarea (error excerpts)
        if (1 === preg_match('/<textarea[^>]*>([^<]*)<\/textarea>/i', $message, $matches)) {
            $excerpt = trim($matches[1]);
            $message = (string) preg_replace('/<textarea[^>]*>[^<]*<\/textarea>/i', '', $message);
            $message = trim($message);
            if ('' !== $excerpt) {
                $message .= ' → `'.$excerpt.'`';
            }
        }

        // Remove any remaining HTML tags
        $message = strip_tags($message);

        // Clean up multiple spaces
        $message = (string) preg_replace('/\s+/', ' ', $message);

        return trim($message);
    }

    private function mustIgnoreError(string $route, string $message): bool
    {
        $plainMessage = strip_tags($message);

        foreach ($this->errorsToIgnore as $pattern) {
            $parts = explode(': ', $pattern, 2);
            if (isset($parts[1])) {
                if (fnmatch($parts[0], $route) && fnmatch($parts[1], $plainMessage)) {
                    return true;
                }
            } elseif (fnmatch($pattern, $plainMessage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A scan takes minutes and only writes its caches at the very end, so a path
     * it cannot write is worth catching before the work, not after losing it.
     * Checked, never cleaned: these names are ours, but a directory sitting on one
     * was put there by something we do not know, and deleting it blind is worse
     * than saying so.
     */
    private function findBlockedCachePath(?string $host): ?string
    {
        $candidates = [PageScannerController::fileCache()];
        if (null !== $host && '' !== $host) {
            $candidates[] = PageScannerController::fileCache().'--'.$host;
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(description: 'Skip external link checks', name: 'skip-external')]
        bool $skipExternal = false,
        #[Option(description: 'Stop after N errors (0 = no limit)', name: 'limit')]
        int $limit = 0,
        #[Option(description: 'Report links pointing to unpublished pages (off by default — those links are hidden at render time)', name: 'check-unpublished')]
        bool $checkUnpublished = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->skipExternal = $skipExternal;
        $this->limit = $limit;
        $this->agentMode = $this->isAgentFormat($format);

        if (null !== ($blocked = $this->findBlockedCachePath($host))) {
            $message = $blocked.' is a directory, but the scan writes its cache there as a file. '
                .'Nothing in Pushword creates it — move it aside, and check what does.';
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:page-scan', 'result' => 'failed', 'message' => $message]);
            } else {
                $output->writeln('<error>'.$message.'</error>');
            }

            return Command::FAILURE;
        }

        if ($checkUnpublished) {
            $this->scanner->linkedDocsScanner->enableCheckUnpublished();
        }

        $processType = null === $host || '' === $host ? self::PROCESS_TYPE : self::PROCESS_TYPE.'--'.$host;

        // Check if same process type is already running (via PID file)
        $pidFile = $this->processManager->getPidFilePath($processType);
        $this->processManager->cleanupStaleProcess($pidFile);
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning'] && null !== $processInfo['pid']) {
            if ($this->agentMode) {
                // Never stream the other process's human output into agent output.
                $this->writeAgentJson($output, [
                    'tool' => 'pw:page-scan',
                    'result' => 'running',
                    'pid' => $processInfo['pid'],
                    'message' => 'A scan is already running; re-run once it completes.',
                ]);

                return Command::SUCCESS;
            }

            return $this->streamRunningOutput($output, $processInfo['pid'], $processType);
        }

        // Register this process and setup shared output
        $this->processManager->registerProcess($pidFile, self::COMMAND_PATTERN);

        // Only clear storage if not already initialized by web controller
        // (web controller sets status to 'running' before starting background process)
        $currentStatus = $this->outputStorage->getStatus($processType);
        if ('running' !== $currentStatus) {
            $this->outputStorage->clear($processType);
            $this->outputStorage->setStatus($processType, 'running');
        }

        // Create tee output to write to both console and shared storage
        $sharedOutput = new SharedOutputInterface($this->outputStorage, $processType);
        $teeOutput = new TeeOutput([$output, $sharedOutput]);
        $this->output = $teeOutput;

        try {
            if (! $this->agentMode) {
                $teeOutput->writeln('<comment>PID: '.getmypid().'</comment>');
            }

            $this->stopwatch = new Stopwatch();
            $this->stopwatch->start('scan');

            $errors = $this->scanAll($host ?? '');
            $cacheFile = null === $host || '' === $host
                ? PageScannerController::fileCache()
                : PageScannerController::fileCache().'--'.$host;
            $this->filesystem->dumpFile($cacheFile, serialize($errors));

            // An aborted loop leaves the graph missing every edge of the pages it
            // never rendered, which would read as orphans. Keep the last good one.
            if ($this->scanCompleted) {
                $this->linkGraphStorage->writeAll(
                    $this->scannedNodes,
                    $this->scanner->linkGraphScanner->getEdges(),
                );
            }

            $event = $this->stopwatch->stop('scan');

            if ($this->agentMode) {
                $this->printAgentSummary($teeOutput, $errors, (int) $event->getDuration());
            } else {
                $teeOutput->writeln(\sprintf('done... (%dms)', $event->getDuration()));
                $this->printTimingBreakdown($teeOutput);
                $teeOutput->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));
            }

            $this->outputStorage->setStatus($processType, 'completed');

            return Command::SUCCESS;
        } finally {
            // Clean up PID file
            $this->processManager->unregisterProcess($pidFile);
        }
    }

    private function printTimingBreakdown(OutputInterface $output): void
    {
        if (null === $this->stopwatch) {
            return;
        }

        $sections = $this->stopwatch->getSections();
        $timings = [];

        foreach ($sections as $section) {
            foreach ($section->getEvents() as $name => $event) {
                // Skip our main event and internal Symfony events
                if ('scan' === $name) {
                    continue;
                }

                if ('__section__' === $name) {
                    continue;
                }

                // Only include our custom timing events
                if (! \in_array($name, ['preload.caches', 'external.urls', 'scanPage'], true)) {
                    continue;
                }

                $timings[$name] = ($timings[$name] ?? 0) + $event->getDuration();
            }
        }

        if ([] === $timings) {
            return;
        }

        arsort($timings);

        $parts = [];
        foreach ($timings as $name => $duration) {
            $shortName = match ($name) {
                'preload.caches' => 'preload',
                'external.urls' => 'external',
                default => 'scan',
            };
            $parts[] = \sprintf('%s: %dms', $shortName, $duration);
        }

        $output->writeln('<comment>⏱ '.implode(' | ', $parts).'</comment>');
    }

    private function streamRunningOutput(OutputInterface $output, int $pid, string $processType): int
    {
        $output->writeln('<info>A page scan is already running (PID: '.$pid.'). Streaming its output...</info>');

        $offset = 0;
        while (true) {
            $result = $this->outputStorage->read($processType, $offset);
            if ('' !== $result['content']) {
                $output->write($result['content']);
                $offset = $result['offset'];
            }

            if ('running' !== $this->outputStorage->getStatus($processType)) {
                break;
            }

            if (! $this->processManager->isProcessAlive($pid, self::COMMAND_PATTERN)) {
                $output->writeln('<error>Process '.$pid.' is no longer running.</error>');

                break;
            }

            usleep(500_000);
        }

        // Final read to capture any output written after last check
        $result = $this->outputStorage->read($processType, $offset);
        if ('' !== $result['content']) {
            $output->write($result['content']);
        }

        return Command::SUCCESS;
    }
}
