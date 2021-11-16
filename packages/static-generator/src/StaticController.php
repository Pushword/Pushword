<?php

namespace Pushword\StaticGenerator;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class StaticController extends AbstractController
{
    /**
     * @Security("is_granted('ROLE_PUSHWORD_ADMIN')")
     */
    public function generateStatic(StaticAppGenerator $staticAppGenerator, ?string $host = null): Response
    {
        $staticAppGenerator->generate($host);

        return $this->render('@pwStaticGenerator/results.html.twig', ['errors' => $staticAppGenerator->getErrors()]);
    }
}
