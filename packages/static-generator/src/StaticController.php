<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    private const string PROCESS_TYPE = 'static-generator';

    private const string COMMAND_PATTERN = 'pw:static';

    public function __construct(
        private readonly BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private readonly BackgroundProcessManager $processManager,
        private readonly ProcessOutputStorage $outputStorage,
        private readonly SiteRegistry $siteRegistry,
    ) {
    }

    private AdminContextProviderInterface $adminContextProvider;

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    #[AdminRoute(
        path: '/static/{host}',
        name: 'static_generator',
        options: ['defaults' => ['host' => null]]
    )]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function generateStatic(?string $host = null): Response
    {
        if (null !== $host && ! $this->isValidHost($host)) {
            throw $this->createNotFoundException('Invalid host parameter');
        }

        $blocking = $this->findBlockingProcess($host);
        if (null !== $blocking) {
            return $this->renderAdmin('@PushwordStatic/running.html.twig', [
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
            return $this->renderAdmin('@PushwordStatic/running.html.twig', [
                'host' => $host,
                'startTime' => $processInfo['startTime'],
                'pending' => false,
                'outputProcessType' => $processType,
            ]);
        }

        // Start new process
        try {
            // Initialize output storage before starting background process
            $this->outputStorage->clear($processType);
            $this->outputStorage->setStatus($processType, 'running');

            $commandParts = ['php', 'bin/console', 'pw:static'];
            if (null !== $host) {
                $commandParts[] = $host;
            }

            $this->backgroundTaskDispatcher->dispatch(
                $processType,
                $commandParts,
                self::COMMAND_PATTERN,
            );
        } catch (Exception $exception) {
            $this->outputStorage->write($processType, 'Failed to start background process: '.$exception->getMessage()."\n");
            $this->outputStorage->setStatus($processType, 'error');
        }

        // Show running page with HTMX polling
        return $this->renderAdmin('@PushwordStatic/running.html.twig', [
            'host' => $host,
            'startTime' => time(),
            'pending' => false,
            'outputProcessType' => $processType,
        ]);
    }

    #[AdminRoute(
        path: '/static-output/{host}',
        name: 'static_generator_output',
        options: ['defaults' => ['host' => '']]
    )]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function getStaticOutput(Request $request, string $host = ''): Response
    {
        $host = '' === $host ? null : $host;

        // Validate host parameter if provided
        if (null !== $host && ! $this->isValidHost($host)) {
            throw $this->createNotFoundException('Invalid host parameter');
        }

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
        $errors = $this->parseErrors($output);

        // Determine status from storage or process state
        $storageStatus = $this->outputStorage->getStatus($outputProcessType);
        if ($isRunning) {
            $status = 'running';
        } elseif ('error' === $storageStatus || [] !== $errors) {
            $status = 'error';
        } else {
            $status = 'completed';
        }

        // If pending and process done, auto-redirect to trigger new generation
        if ($pending && 'running' !== $status) {
            $response = new Response('', Response::HTTP_OK);
            $params = null !== $host ? ['host' => $host] : [];
            $response->headers->set('HX-Redirect', $this->generateUrl('admin_static_generator', $params));

            return $response;
        }

        $response = $this->render('@PushwordStatic/output_fragment.html.twig', [
            'status' => $status,
            'output' => $output,
            'errors' => $errors,
            'host' => $host,
            'pending' => $pending,
            'outputProcessType' => $outputProcessType,
        ]);

        // Stop HTMX polling when process is complete
        if ('running' !== $status) {
            $response->headers->set('HX-Reswap', 'innerHTML');
        }

        return $response;
    }

    private function getProcessType(?string $host): string
    {
        return null === $host ? self::PROCESS_TYPE : self::PROCESS_TYPE.'--'.$host;
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

    private function isValidHost(string $host): bool
    {
        // Basic validation - adjust based on your needs
        return 1 === preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $host);
    }

    /**
     * @return array<string>
     */
    private function parseErrors(string $output): array
    {
        $errors = [];

        if ('' === $output) {
            return $errors;
        }

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            $lowerLine = strtolower($line);

            if ('' !== $line && (
                str_contains($lowerLine, 'error')
                || str_contains($lowerLine, 'failed')
                || str_contains($lowerLine, 'exception')
            )) {
                $errors[] = $line;
            }
        }

        return $errors;
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
