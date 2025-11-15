<?php

namespace Pushword\StaticGenerator;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    #[Route(path: '/~static', name: 'old_piedweb_static_generate', methods: ['GET'], priority: 1)]
    public function redirectOldStaticRoute(): RedirectResponse
    {
        return $this->redirectToRoute('piedweb_static_generate', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/{host}', name: 'piedweb_static_generate', methods: ['GET'], priority: -1)]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function generateStatic(StaticAppGenerator $staticAppGenerator, ?string $host = null): Response
    {
        exec('cd ../ && php bin/console pushword:static:generate '.((string) $host).' > /dev/null 2>/dev/null &');
        // $staticAppGenerator->generate($host); // TODO : fixed why it's logged me out

        return $this->render('@PushwordStatic/results.html.twig', ['errors' => $staticAppGenerator->getErrors()]);
    }
}
