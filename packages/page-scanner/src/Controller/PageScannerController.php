<?php

namespace Pushword\PageScanner\Controller;

use DateInterval;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use LogicException;
use Pushword\Core\Utils\LastTime;

use function Safe\file_get_contents;
use function Safe\filemtime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class PageScannerController extends AbstractController
{
    private static ?string $fileCache = null;

    public function __construct(
        private readonly Filesystem $filesystem,
        string $varDir,
        private readonly string $pageScanInterval,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        self::setFileCache($varDir);
    }

    private AdminContextProviderInterface $adminContextProvider;

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    public static function setFileCache(string $varDir): void
    {
        self::$fileCache = null !== self::$fileCache && '' !== self::$fileCache ? self::$fileCache : $varDir.'/page-scan';
    }

    public static function fileCache(): string
    {
        if (! \is_string(self::$fileCache)) {
            throw new Exception('setFileCache($varDir) must be setted before call fileCache()');
        }

        return self::$fileCache;
    }

    #[AdminRoute(
        path: '/scan/{force}',
        name: 'page_scanner',
        options: ['defaults' => ['force' => 0]]
    )]
    #[Route(path: '/scan/{force}', name: 'pushword_page_scanner', defaults: ['force' => 0], methods: ['GET'])]
    public function scan(int $force = 0): Response
    {
        $force = (bool) $force;

        if (null === self::$fileCache) {
            throw new LogicException();
        }

        if ($this->filesystem->exists(self::$fileCache)) {
            $errors = unserialize(file_get_contents(self::$fileCache));
            $lastEdit = filemtime(self::$fileCache);
        } else {
            $lastEdit = 0;
            $errors = [];
        }

        $lastTime = new LastTime(self::$fileCache);
        if ($force || ! $lastTime->wasRunSince(new DateInterval($this->pageScanInterval))) {
            $this->runBackgroundPageScan();
            $newRunLaunched = true;
            $lastTime->setWasRun('now', false);
        }

        return $this->renderAdmin('@pwPageScanner/results.html.twig', [
            'newRun' => $newRunLaunched ?? false,
            'lastEdit' => $lastEdit,
            'errorsByPages' => $errors,
        ]);
    }

    /**
     * Run page scan command in background using Symfony Process.
     * This prevents command injection vulnerabilities.
     */
    private function runBackgroundPageScan(): void
    {
        $process = new Process([
            'php',
            'bin/console',
            'pw:page-scan',
        ]);
        $process->setWorkingDirectory($this->projectDir);
        $process->disableOutput();
        $process->start();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderAdmin(string $view, array $parameters = []): Response
    {
        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            throw new LogicException('EasyAdmin context is not available for this request.');
        }

        $parameters['ea'] = $context;

        return $this->render($view, $parameters);
    }
}
