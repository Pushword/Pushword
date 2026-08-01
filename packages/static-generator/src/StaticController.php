<?php

namespace Pushword\StaticGenerator;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    public function __construct(
        private readonly StaticGenerationCoordinator $coordinator,
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

        $blocking = $this->coordinator->findBlockingProcess($host);
        if (null !== $blocking) {
            return $this->renderAdmin('@PushwordStatic/running.html.twig', [
                'host' => $host,
                'startTime' => $blocking['startTime'],
                'pending' => true,
                'outputProcessType' => $blocking['processType'],
            ]);
        }

        $processType = $this->coordinator->getProcessType($host);
        $processInfo = $this->coordinator->getProcessInfo($processType);

        if ($processInfo['isRunning']) {
            return $this->renderAdmin('@PushwordStatic/running.html.twig', [
                'host' => $host,
                'startTime' => $processInfo['startTime'],
                'pending' => false,
                'outputProcessType' => $processType,
            ]);
        }

        $this->coordinator->startGeneration($host);

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
        $outputProcessType = $request->query->getString('pt', '') ?: $this->coordinator->getProcessType($host);

        $state = $this->coordinator->readOutput($outputProcessType);
        $status = $state['status'];

        // If pending and process done, auto-redirect to trigger new generation
        if ($pending && 'running' !== $status) {
            $response = new Response('', Response::HTTP_OK);
            $params = null !== $host ? ['host' => $host] : [];
            $response->headers->set('HX-Redirect', $this->generateUrl('admin_static_generator', $params));

            return $response;
        }

        $response = $this->render('@PushwordStatic/output_fragment.html.twig', [
            'status' => $status,
            'output' => $state['output'],
            'errors' => $state['errors'],
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

    private function isValidHost(string $host): bool
    {
        // Basic validation - adjust based on your needs
        return 1 === preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $host);
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
