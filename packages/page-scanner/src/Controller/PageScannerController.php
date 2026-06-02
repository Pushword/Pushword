<?php

namespace Pushword\PageScanner\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use Pushword\PageScanner\Service\PageScanCoordinator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class PageScannerController extends AbstractController
{
    private static ?string $fileCache = null;

    public function __construct(
        private readonly PageScanCoordinator $coordinator,
        string $varDir,
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
        $host = $request->query->getString('host', '') ?: null;

        // Check for cross-lock
        $blocking = $this->coordinator->findBlockingProcess($host);
        if (null !== $blocking) {
            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'host' => $host,
                'startTime' => $blocking['startTime'],
                'pending' => true,
                'outputProcessType' => $blocking['processType'],
            ]);
        }

        $processType = $this->coordinator->getProcessType($host);

        // Check if a process is already running
        $processInfo = $this->coordinator->getProcessInfo($processType);
        if ($processInfo['isRunning']) {
            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'host' => $host,
                'startTime' => $processInfo['startTime'],
                'pending' => false,
                'outputProcessType' => $processType,
            ]);
        }

        // Start new scan if forced or interval exceeded
        if ($this->coordinator->shouldScan($host, (bool) $force)) {
            $this->coordinator->startScan($host);

            return $this->renderAdmin('@pwPageScanner/scanning.html.twig', [
                'host' => $host,
                'startTime' => time(),
                'pending' => false,
                'outputProcessType' => $processType,
            ]);
        }

        $results = $this->coordinator->readResults($host);

        return $this->renderAdmin('@pwPageScanner/results.html.twig', [
            'host' => $host,
            'newRun' => false,
            'lastEdit' => $results['lastEdit'],
            'errorsByPages' => $results['errorsByPages'],
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
        $outputProcessType = $request->query->getString('pt', '') ?: $this->coordinator->getProcessType($host);

        $state = $this->coordinator->readOutput($outputProcessType);

        // If pending and process done, auto-redirect to trigger new scan
        if ($pending && 'running' !== $state['status']) {
            $response = new Response('', Response::HTTP_OK);
            $params = null !== $host ? ['host' => $host] : [];
            $params['force'] = 1;
            $response->headers->set('HX-Redirect', $this->generateUrl('admin_page_scanner', $params));

            return $response;
        }

        return $this->render('@pwPageScanner/output_fragment.html.twig', [
            'status' => $state['status'],
            'output' => $state['output'],
            'host' => $host,
            'pending' => $pending,
            'outputProcessType' => $outputProcessType,
        ]);
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
