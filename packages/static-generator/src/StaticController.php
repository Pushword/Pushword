<?php

namespace Pushword\StaticGenerator;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    private const string PROCESS_TYPE = 'static-generator';

    private const string COMMAND_PATTERN = 'pw:static';

    public function __construct(
        private readonly BackgroundProcessManager $processManager,
        private readonly ProcessOutputStorage $outputStorage,
    ) {
    }

    private AdminContextProviderInterface $adminContextProvider;

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    #[Route(path: '/~static', name: 'old_piedweb_static_generate', methods: ['GET'], priority: 1)]
    public function redirectOldStaticRoute(): RedirectResponse
    {
        return $this->redirectToRoute('piedweb_static_generate', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[AdminRoute(
        path: '/static/{host}',
        name: 'static_generator',
        options: ['defaults' => ['host' => null]]
    )]
    #[Route(path: '/{host}', name: 'piedweb_static_generate', defaults: ['host' => null], methods: ['GET'], priority: -1)]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function generateStatic(?string $host = null): Response
    {
        // Validate host parameter if provided
        if (null !== $host && ! $this->isValidHost($host)) {
            throw $this->createNotFoundException('Invalid host parameter');
        }

        $pidFile = $this->processManager->getPidFilePath(self::PROCESS_TYPE);

        // Clean up stale processes
        $this->processManager->cleanupStaleProcess($pidFile);

        // Check if a process is already running
        $processInfo = $this->processManager->getProcessInfo($pidFile);

        if ($processInfo['isRunning']) {
            return $this->renderAdmin('@PushwordStatic/running.html.twig', [
                'host' => $host,
                'startTime' => $processInfo['startTime'],
            ]);
        }

        // Check if we should display results (status exists and not running)
        $status = $this->outputStorage->getStatus(self::PROCESS_TYPE);
        if (null !== $status && 'running' !== $status) {
            $outputData = $this->outputStorage->read(self::PROCESS_TYPE);
            $output = $outputData['content'];
            $errors = $this->parseErrors($output);

            // Clean up storage
            $this->outputStorage->clear(self::PROCESS_TYPE);

            return $this->renderAdmin('@PushwordStatic/results.html.twig', [
                'errors' => $errors,
                'output' => $output,
                'host' => $host,
            ]);
        }

        // Start new process
        try {
            // Initialize output storage before starting background process
            $this->outputStorage->clear(self::PROCESS_TYPE);
            $this->outputStorage->setStatus(self::PROCESS_TYPE, 'running');

            $commandParts = ['php', 'bin/console', 'pw:static'];
            if (null !== $host) {
                $commandParts[] = $host;
            }

            $this->processManager->startBackgroundProcess(
                $pidFile,
                $commandParts,
                self::COMMAND_PATTERN,
            );
        } catch (Exception $exception) {
            $this->outputStorage->clear(self::PROCESS_TYPE);

            throw new RuntimeException('Failed to start background process: '.$exception->getMessage(), 0, $exception);
        }

        // Show running page with HTMX polling
        return $this->renderAdmin('@PushwordStatic/running.html.twig', [
            'host' => $host,
            'startTime' => time(),
        ]);
    }

    #[AdminRoute(
        path: '/static-output/{host}',
        name: 'static_generator_output',
        options: ['defaults' => ['host' => '']]
    )]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function getStaticOutput(string $host = ''): Response
    {
        $host = '' === $host ? null : $host;

        // Validate host parameter if provided
        if (null !== $host && ! $this->isValidHost($host)) {
            throw $this->createNotFoundException('Invalid host parameter');
        }

        $pidFile = $this->processManager->getPidFilePath(self::PROCESS_TYPE);

        // Clean up stale processes
        $this->processManager->cleanupStaleProcess($pidFile);

        // Check if process is running
        $processInfo = $this->processManager->getProcessInfo($pidFile);
        $isRunning = $processInfo['isRunning'];

        // Get full output from shared storage
        $outputData = $this->outputStorage->read(self::PROCESS_TYPE);
        $output = $outputData['content'];
        $errors = $this->parseErrors($output);

        // Determine status from storage or process state
        $storageStatus = $this->outputStorage->getStatus(self::PROCESS_TYPE);
        if ($isRunning) {
            $status = 'running';
        } elseif ('error' === $storageStatus || [] !== $errors) {
            $status = 'error';
        } else {
            $status = 'completed';
        }

        $response = $this->render('@PushwordStatic/output_fragment.html.twig', [
            'status' => $status,
            'output' => $output,
            'errors' => $errors,
            'host' => $host,
        ]);

        // Stop HTMX polling when process is complete
        if ('running' !== $status) {
            $response->headers->set('HX-Reswap', 'innerHTML');
        }

        return $response;
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
        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            throw new RuntimeException('EasyAdmin context is not available for this request.');
        }

        $parameters['ea'] = $context;

        return $this->render($view, $parameters);
    }
}
