<?php

declare(strict_types=1);

namespace Pushword\Facebook\Tests;

use Pushword\Facebook\TwigExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TwigExtensionTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();
        $twig = new TwigExtension();
        $twig->setApps(self::$kernel->getContainer()->get('pushword.apps'));
        $twigEnv = self::$kernel->getContainer()->get('twig');
        $this->assertIsString($twig->showFacebookLastPost($twigEnv, 'Google'));
    }
}
