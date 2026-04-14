<?php

namespace Pushword\PageScanner\Controller;

use DateInterval;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\LastTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly SiteRegistry $siteRegistry,
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
    public function scan(Request $request, int $force = 0): Response
    {
        $force = (bool) $force;
        $host = $request->query->getString('host', '') ?: null;

        // Check for cross-lock
        $blocking = $this->findBlockingProcess($host);
        if (null !== $blocking) {
            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'host' => $host,
                'startTime' => $blocking['startTime'],
                'pending' => true,
                'outputProcessType' => $blocking['processType'],
            ]);
        }

        $processType = $this->getProcessType($host);
        $pidFile = $this->processManager->getPidFilePath($processType);

        // Clean up stale processes
        $this->processManager->cleanupStaleProcess($pidFile);

        // Check if a process is already running
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'host' => $host,
                'startTime' => $processInfo['startTime'],
                'pending' => false,
                'outputProcessType' => $processType,
            ]);
        }

        // Check for existing results
        $fileCache = $this->getFileCache($host);
        if ($this->filesystem->exists($fileCache)) {
            /** @var array<int, array<int, array{page: array{host: string, slug: string}, message: string}>> */
            $errors = unserialize($this->filesystem->readFile($fileCache));
            $lastEdit = filemtime($fileCache);
        } else {
            $lastEdit = 0;
            $errors = [];
        }

        // Start new scan if forced or interval exceeded
        $lastTime = new LastTime($fileCache);
        if ($force || ! $lastTime->wasRunSince(new DateInterval($this->pageScanInterval))) {
            // Initialize output storage before starting background process
            $this->outputStorage->clear($processType);
            $this->outputStorage->setStatus($processType, 'running');

            $commandParts = ['php', 'bin/console', 'pw:page-scan'];
            if (null !== $host) {
                $commandParts[] = $host;
            }

            try {
                $this->backgroundTaskDispatcher->dispatch(
                    $processType,
                    $commandParts,
                    self::COMMAND_PATTERN,
                );
            } catch (Exception $exception) {
                $this->outputStorage->write($processType, 'Failed to start background process: '.$exception->getMessage()."\n");
                $this->outputStorage->setStatus($processType, 'error');
            }

            $lastTime->setWasRun('now', false);

            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'host' => $host,
                'startTime' => time(),
                'pending' => false,
                'outputProcessType' => $processType,
            ]);
        }

        return $this->renderAdmin('@pwPageScanner/results.html.twig', [
            'host' => $host,
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
    public function getScanOutput(Request $request): Response
    {
        $host = $request->query->getString('host', '') ?: null;
        $pending = $request->query->getBoolean('pending');
        $outputProcessType = $request->query->getString('pt', '') ?: $this->getProcessType($host);

        $pidFile = $this->processManager->getPidFilePath($outputProcessType);

        // Clean up stale processes
        $this->processManager->cleanupStaleProcess($pidFile);

        // Check if process is running
        $processInfo = $this->processManager->getProcessInfo($pidFile);
        $isRunning = $processInfo['isRunning'];

        // Get full output from shared storage
        $outputData = $this->outputStorage->read($outputProcessType);
        $output = $outputData['content'];

        // Determine status from storage or process state
        $storageStatus = $this->outputStorage->getStatus($outputProcessType);
        $status = $isRunning ? 'running' : ($storageStatus ?? 'completed');

        // If pending and process done, auto-redirect to trigger new scan
        if ($pending && 'running' !== $status) {
            $response = new Response('', Response::HTTP_OK);
            $params = null !== $host ? ['host' => $host] : [];
            $params['force'] = 1;
            $response->headers->set('HX-Redirect', $this->generateUrl('admin_page_scanner', $params));

            return $response;
        }

        return $this->render('@pwPageScanner/output_fragment.html.twig', [
            'status' => $status,
            'output' => $output,
            'host' => $host,
            'pending' => $pending,
            'outputProcessType' => $outputProcessType,
        ]);
    }

    private function getProcessType(?string $host): string
    {
        return null === $host ? self::PROCESS_TYPE : self::PROCESS_TYPE.'--'.$host;
    }

    private function getFileCache(?string $host): string
    {
        $base = self::fileCache();

        return null === $host ? $base : $base.'--'.$host;
    }

    /** @return array{startTime: int|null, processType: string}|null */
    private function findBlockingProcess(?string $host): ?array
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
     * @return array{startTime: int|null, processType: string}|null
     */
    private function checkProcessRunning(string $processType): ?array
    {
        $pidFile = $this->processManager->getPidFilePath($processType);
        $this->processManager->cleanupStaleProcess($pidFile);
        $info = $this->processManager->getProcessInfo($pidFile);

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

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderAdmin(string $view, array $parameters = []): Response
    {
        $parameters['ea'] = $this->adminContextProvider->getContext();

        return $this->render($view, $parameters);
    }
}
