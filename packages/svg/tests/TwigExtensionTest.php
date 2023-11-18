<?php

declare(strict_types=1);

namespace Pushword\Svg\Tests;

use Pushword\Svg\TwigExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TwigExtensionTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();
        $twig = new TwigExtension();
        $twig->setApps(self::$kernel->getContainer()->get(\Pushword\Core\Component\App\AppPool::class));
        $this->assertStringStartsWith('<svg', $twig->getSvg('facebook'));
    }
}
