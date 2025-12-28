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
    protected function scanAll(string $host): array
    {
        $this->scanner->preloadCaches($host);
        $pages = $this->pageRepo->getPublishedPages($host);
        $pagesCount = \count($pages);

        // Single pass: scan all pages, collect internal errors AND external URLs
        $this->output?->writeln(\sprintf('Scanning %d pages...', $pagesCount));
        $this->scanner->linkedDocsScanner->enableDeferredExternalMode();

        $errors = [];
        $errorNbr = 0;
        $currentPage = 0;

        foreach ($pages as $page) {
            sleep(2);
            ++$currentPage;
            $pageSlug = $page->getSlug() ?: 'index';
            $pageHost = $page->host ?? '';

            $this->output?->writeln(\sprintf(
                '[%d/%d] Scanning %s/%s',
                $currentPage,
                $pagesCount,
                $pageHost,
                $pageSlug,
            ));

            $scan = $this->scanner->scan($page);
            if (true !== $scan) {
                $pageId = (int) $page->id;
                $errors[$pageId] = $scan;
                foreach ($scan as $s) {
                    $route = $s['page']['host'].'/'.$s['page']['slug'];
                    if (! $this->mustIgnoreError($route, $s['message'])) {
                        $this->output?->writeln('  ➜ '.str_replace(['<code>', '</code>'], '`', $s['message']));
                    }
                }

                $errorNbr += \count($errors[$pageId]);
            }

            if ($errorNbr > 500) {
                $this->output?->writeln('Too many errors (>500), stopping scan...');

                break;
            }
        }

        // Parallel external URL validation
        if (! $this->skipExternal) {
            $externalUrls = $this->scanner->linkedDocsScanner->getCollectedExternalUrls();
            $urlCount = \count($externalUrls);
            if ($urlCount > 0) {
                $this->output?->writeln(\sprintf('Checking %d external URLs in parallel...', $urlCount));
                $urlResults = $this->parallelUrlChecker->checkUrls($externalUrls);
                $this->scanner->linkedDocsScanner->setExternalUrlResults($urlResults);
            }
        }

        // Resolve deferred external URL errors
        $externalErrors = $this->skipExternal ? [] : $this->scanner->linkedDocsScanner->resolveDeferredExternalErrors();
        foreach ($externalErrors as $pageId => $pageErrors) {
            foreach ($pageErrors as $error) {
                $route = $error['page']['host'].'/'.$error['page']['slug'];
                if (! $this->mustIgnoreError($route, $error['message'])) {
                    $this->output?->writeln($route.' ➜ '.str_replace(['<code>', '</code>'], '`', $error['message']));
                }
            }

            $errors[$pageId] = [...($errors[$pageId] ?? []), ...$pageErrors];
        }

        $this->scanner->linkedDocsScanner->disableDeferredExternalMode();

        return $errors;
    }

    private ?OutputInterface $output = null;

    private bool $skipExternal = false;

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
            $errors = $this->scanAll($host ?? '');
            $this->filesystem->dumpFile(PageScannerController::fileCache(), serialize($errors));
            $teeOutput->writeln('done...');
            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'completed');

            return Command::SUCCESS;
        } finally {
            // Clean up PID file
            $this->processManager->unregisterProcess($pidFile);
        }
    }
}
