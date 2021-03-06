<?php

namespace Pushword\StaticGenerator;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StaticController extends AbstractController
{
    /**
     * @Security("is_granted('ROLE_PUSHWORD_ADMIN')")
     */
    public function generateStatic(StaticAppGenerator $staticAppGenerator, ?string $host = null)
    {
        $staticAppGenerator->generate($host);

        return $this->render('@pwStaticGenerator/results.html.twig');
    }
}
