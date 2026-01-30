<?php

namespace Pushword\PageScanner\Controller;

use DateInterval;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use LogicException;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Utils\LastTime;

use function Safe\file_get_contents;
use function Safe\filemtime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class PageScannerController extends AbstractController
{
    private static ?string $fileCache = null;

    private const string PROCESS_TYPE = 'page-scanner';

    private const string COMMAND_PATTERN = 'pw:page-scan';

    /**
     * @param string[] $errorsToIgnore
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        string $varDir,
        private readonly string $pageScanInterval,
        private readonly BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private readonly BackgroundProcessManager $processManager,
        private readonly ProcessOutputStorage $outputStorage,
        private readonly array $errorsToIgnore = [],
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
        $fileCache = self::fileCache();

        $pidFile = $this->processManager->getPidFilePath(self::PROCESS_TYPE);

        // Clean up stale processes
        $this->processManager->cleanupStaleProcess($pidFile);

        // Check if a process is already running
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'startTime' => $processInfo['startTime'],
            ]);
        }

        // Check for existing results
        if ($this->filesystem->exists($fileCache)) {
            /** @var array<int, array<int, array{page: array{host: string, slug: string}, message: string}>> */
            $errors = unserialize(file_get_contents($fileCache));
            $lastEdit = filemtime($fileCache);
        } else {
            $lastEdit = 0;
            $errors = [];
        }

        // Start new scan if forced or interval exceeded
        $lastTime = new LastTime($fileCache);
        if ($force || ! $lastTime->wasRunSince(new DateInterval($this->pageScanInterval))) {
            // Initialize output storage before starting background process
            $this->outputStorage->clear(self::PROCESS_TYPE);
            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'running');

            $this->backgroundTaskDispatcher->dispatch(
                self::PROCESS_TYPE,
                ['php', 'bin/console', 'pw:page-scan'],
                self::COMMAND_PATTERN,
            );
            $lastTime->setWasRun('now', false);

            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'startTime' => time(),
            ]);
        }

        return $this->renderAdmin('@pwPageScanner/results.html.twig', [
            'newRun' => false,
            'lastEdit' => $lastEdit,
            'errorsByPages' => $this->filterErrors($errors),
        ]);
    }

    #[AdminRoute(
        path: '/scan-output',
        name: 'page_scanner_output'
    )]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function getScanOutput(): Response
    {
        $pidFile = $this->processManager->getPidFilePath(self::PROCESS_TYPE);

        // Clean up stale processes
        $this->processManager->cleanupStaleProcess($pidFile);

        // Check if process is running
        $processInfo = $this->processManager->getProcessInfo($pidFile);
        $isRunning = $processInfo['isRunning'];

        // Get full output from shared storage
        $outputData = $this->outputStorage->read(self::PROCESS_TYPE);
        $output = $outputData['content'];

        // Determine status from storage or process state
        $storageStatus = $this->outputStorage->getStatus(self::PROCESS_TYPE);
        $status = $isRunning ? 'running' : ($storageStatus ?? 'completed');

        return $this->render('@pwPageScanner/output_fragment.html.twig', [
            'status' => $status,
            'output' => $output,
        ]);
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
