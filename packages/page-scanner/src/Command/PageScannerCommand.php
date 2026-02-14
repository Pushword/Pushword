<?php

namespace Pushword\PageScanner\Command;

use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Service\SharedOutputInterface;
use Pushword\Core\Service\TeeOutput;
use Pushword\PageScanner\Controller\PageScannerController;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ParallelUrlChecker;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'pw:page-scan',
    description: 'Find dead links, 404, 301 and more in your content.'
)]
final class PageScannerCommand
{
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
        private readonly array $errorsToIgnore,
        string $varDir,
    ) {
        PageScannerController::setFileCache($varDir);
    }

    /**
     * @return array<int, mixed[]>
     */
    private function scanAll(string $host): array
    {
        $this->stopwatch?->start('preload.caches');
        $this->scanner->preloadCaches($host);
        $this->stopwatch?->stop('preload.caches');

        $pages = $this->pageRepo->getPublishedPages($host);
        $pagesCount = \count($pages);

        // Single pass: scan all pages, collect internal errors AND external URLs
        $this->output?->writeln(\sprintf('Scanning %d pages...', $pagesCount));
        $this->scanner->linkedDocsScanner->enableDeferredExternalMode();

        $errors = [];
        $errorNbr = 0;
        $currentPage = 0;
        $lastLineWasError = false;

        foreach ($pages as $page) {
            ++$currentPage;
            $pageSlug = $page->getSlug() ?: 'index';
            $pageHost = $page->host ?? '';

            // Progress indicator: overwrite line unless previous was an error
            if ($lastLineWasError) {
                $this->output?->write(\sprintf('Scanning... [%d/%d]', $currentPage, $pagesCount));
            } else {
                $this->output?->write(\sprintf("\rScanning... [%d/%d]", $currentPage, $pagesCount));
            }

            $lastLineWasError = false;

            $this->stopwatch?->start('scanPage');
            $this->stopwatch?->start('scan:'.$page->getSlug());
            $scan = $this->scanner->scan($page);
            $event = $this->stopwatch?->stop('scan:'.$page->getSlug());
            $this->stopwatch?->stop('scanPage');

            if (null !== $event && $event->getDuration() > 500 && null !== $this->output && $this->output->isVerbose()) {
                $this->output->writeln("\n".\sprintf('<comment>⏱ %s/%s: %dms (slow)</comment>', $pageHost, $pageSlug, $event->getDuration()));
                $lastLineWasError = true;
            }

            if (true !== $scan) {
                $pageId = (int) $page->id;
                $errors[$pageId] = $scan;

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

                $errorNbr += \count($errors[$pageId]);
            }

            if ($errorNbr > 500) {
                $this->output?->writeln("\n".'Too many errors (>500), stopping scan...');

                break;
            }
        }

        if (! $lastLineWasError) {
            $this->output?->writeln('');
        }

        // Parallel external URL validation
        if (! $this->skipExternal) {
            $externalUrls = $this->scanner->linkedDocsScanner->getCollectedExternalUrls();
            $urlCount = \count($externalUrls);
            if ($urlCount > 0) {
                $this->output?->writeln(\sprintf('Checking %d external URLs in parallel...', $urlCount));
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
                if (! $this->mustIgnoreError($route, $error['message'])) {
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

    private ?Stopwatch $stopwatch = null;

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

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(description: 'Skip external link checks', name: 'skip-external')]
        bool $skipExternal = false,
    ): int {
        $this->skipExternal = $skipExternal;

        // Check if same process type is already running (via PID file)
        $pidFile = $this->processManager->getPidFilePath(self::PROCESS_TYPE);
        $this->processManager->cleanupStaleProcess($pidFile);
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            $output->writeln('<error>A page scan is already running (PID: '.$processInfo['pid'].').</error>');

            return Command::FAILURE;
        }

        // Register this process and setup shared output
        $this->processManager->registerProcess($pidFile, self::COMMAND_PATTERN);

        // Only clear storage if not already initialized by web controller
        // (web controller sets status to 'running' before starting background process)
        $currentStatus = $this->outputStorage->getStatus(self::PROCESS_TYPE);
        if ('running' !== $currentStatus) {
            $this->outputStorage->clear(self::PROCESS_TYPE);
            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'running');
        }

        // Create tee output to write to both console and shared storage
        $sharedOutput = new SharedOutputInterface($this->outputStorage, self::PROCESS_TYPE);
        $teeOutput = new TeeOutput([$output, $sharedOutput]);
        $this->output = $teeOutput;

        try {
            $teeOutput->writeln('<comment>PID: '.getmypid().'</comment>');

            $this->stopwatch = new Stopwatch();
            $this->stopwatch->start('scan');

            $errors = $this->scanAll($host ?? '');
            $this->filesystem->dumpFile(PageScannerController::fileCache(), serialize($errors));

            $event = $this->stopwatch->stop('scan');
            $teeOutput->writeln(\sprintf('done... (%dms)', $event->getDuration()));

            // Print timing breakdown
            $this->printTimingBreakdown($teeOutput);

            $teeOutput->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'completed');

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
}
