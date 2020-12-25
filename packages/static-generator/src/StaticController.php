<?php

namespace Pushword\StaticGenerator;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StaticController extends AbstractController
{
    /**
     * @Security("is_granted('ROLE_EDITOR')")
     */
    public function generateStatic(StaticAppGenerator $staticAppGenerator)
    {
        $staticAppGenerator->generateAll();

        return $this->render('@pwStaticGenerator/results.html.twig');
    }
}
