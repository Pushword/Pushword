<?php

namespace Pushword\StaticGenerator;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function generateStatic(StaticAppGenerator $staticAppGenerator, ?string $host = null): Response
    {
        exec('cd ../ && php bin/console pushword:static:generate '.((string) $host).' > /dev/null 2>/dev/null &');
        // $staticAppGenerator->generate($host); // TODO : fixed why it's logged me out

        return $this->render('@pwStaticGenerator/results.html.twig', ['errors' => $staticAppGenerator->getErrors()]);
    }
}
