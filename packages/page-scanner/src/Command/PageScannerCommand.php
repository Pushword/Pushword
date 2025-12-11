<?php

namespace Pushword\PageScanner\Command;

use Pushword\Core\Repository\PageRepository;
use Pushword\PageScanner\Controller\PageScannerController;
use Pushword\PageScanner\Scanner\PageScannerService;
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
        string $varDir,
    ) {
        PageScannerController::setFileCache($varDir);
    }


    protected function scanAllWithLock(string $host): bool
    {
        $lock = (new LockFactory(new FlockStore()))->createLock('page-scan');
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
        $pages = $this->pageRepo->getPublishedPagesWithEagerLoading($host);

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
