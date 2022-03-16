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
        exec('cd ../ && php bin/console pushword:static:generate '.$host.' > /dev/null 2>/dev/null &');
        // $staticAppGenerator->generate($host); // TODO : fixed why it's logged me out

        return $this->render('@pwStaticGenerator/results.html.twig', ['errors' => $staticAppGenerator->getErrors()]);
    }
}
