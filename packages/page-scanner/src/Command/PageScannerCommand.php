<?php

namespace Pushword\PageScanner\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\PageScanner\Controller\PageScannerController;
use Pushword\PageScanner\Scanner\PageScannerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class PageScannerCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'pushword:page-scanner:scan';

    /**
     * @param class-string<PageInterface> $pageClass
     */
    public function __construct(
        private PageScannerService $scanner,
        private Filesystem $filesystem,
        private EntityManagerInterface $em,
        private string $pageClass,
        string $varDir
    ) {
        parent::__construct();
        PageScannerController::setFileCache($varDir);
    }

    protected function configure(): void
    {
        $this->setDescription('Find dead links, 404, 301 and more in your content.')
            ->addArgument('host', InputArgument::OPTIONAL, '');
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
        $pages = Repository::getPageRepository($this->em, $this->pageClass)->getPublishedPages($host);

        $errors = [];
        $errorNbr = 0;

        foreach ($pages as $page) {
            $scan = $this->scanner->scan($page);
            if (true !== $scan) {
                $errors[(int) $page->getId()] = $scan;
                $errorNbr += \count($errors[$page->getId()]);
            }

            if ($errorNbr > 500) {
                break;
            }
        }

        return $errors;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Acquiring page scanner lock to start the scan...');

        if ($this->scanAllWithLock($input->getArgument('host') ?? '')) {
            $output->writeln('done...');
        } else {
            $output->writeln('cannot acquiring the page scanner lock...');
        }

        return 0;
    }
}
