<?php

namespace Pushword\PageScanner\Command;

use Pushword\Core\Repository\PageRepository;
use Pushword\PageScanner\Controller\PageScannerController;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ParallelUrlChecker;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

#[AsCommand(name: 'pw:page-scan', description: 'Find dead links, 404, 301 and more in your content.')]
final class PageScannerCommand
{
    public function __construct(
        private readonly PageScannerService $scanner,
        private readonly Filesystem $filesystem,
        private readonly PageRepository $pageRepo,
        private readonly ParallelUrlChecker $parallelUrlChecker,
        string $varDir,
    ) {
        PageScannerController::setFileCache($varDir);
    }

    protected function scanAllWithLock(string $host): bool
    {
        $lock = new LockFactory(new FlockStore())->createLock('page-scan');
        if ($lock->acquire()) {
            // sleep(30);
            $errors = $this->scanAll($host);
            // dd($errors);
            $this->filesystem->dumpFile(PageScannerController::fileCache(), serialize($errors));
            $lock->release();

            return true;
        }

        return false;
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

        foreach ($pages as $page) {
            $scan = $this->scanner->scan($page);
            if (true !== $scan) {
                $pageId = (int) $page->getId();
                $errors[$pageId] = $scan;
                foreach ($scan as $s) {
                    $this->output?->writeln($s['page']['host'].'/'.$s['page']['slug'].' ➜ '.str_replace(['<code>', '</code>'], '`', $s['message']));
                }

                $errorNbr += \count($errors[$pageId]);
            }

            if ($errorNbr > 500) {
                break;
            }
        }

        // Parallel external URL validation
        $externalUrls = $this->scanner->linkedDocsScanner->getCollectedExternalUrls();
        $urlCount = \count($externalUrls);
        if ($urlCount > 0) {
            $this->output?->writeln(\sprintf('Checking %d external URLs in parallel...', $urlCount));
            $urlResults = $this->parallelUrlChecker->checkUrls($externalUrls);
            $this->scanner->linkedDocsScanner->setExternalUrlResults($urlResults);
        }

        // Resolve deferred external URL errors
        $externalErrors = $this->scanner->linkedDocsScanner->resolveDeferredExternalErrors();
        foreach ($externalErrors as $pageId => $pageErrors) {
            foreach ($pageErrors as $error) {
                $this->output?->writeln($error['page']['host'].'/'.$error['page']['slug'].' ➜ '.str_replace(['<code>', '</code>'], '`', $error['message']));
            }

            $errors[$pageId] = [...($errors[$pageId] ?? []), ...$pageErrors];
        }

        $this->scanner->linkedDocsScanner->disableDeferredExternalMode();

        return $errors;
    }

    private ?OutputInterface $output = null;

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
    ): int {
        $output->writeln('Acquiring page scanner lock to start the scan...');
        $this->output = $output;

        if ($this->scanAllWithLock($host ?? '')) {
            $output->writeln('done...');
        } else {
            $output->writeln('cannot acquiring the page scanner lock...');
        }

        return Command::SUCCESS;
    }
}
