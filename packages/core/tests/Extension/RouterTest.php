<?php

namespace Pushword\Core\Tests\Extension;

use Pushword\Core\Component\Router\Router;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class RouterTest extends KernelTestCase
{
    public function testRouter()
    {
        self::bootKernel();

        $router = new Router(
            self::$kernel->getContainer()->get('router'),
            self::$kernel->getContainer()->get('pushword.apps'),
            new RequestStack(),
            'fr'
        );

        $this->assertSame('/', $router->generatePathForHomePage());
        $this->assertSame('/', $router->generate('homepage'));
        $this->assertSame('/page', $router->generate('page'));
    }

    public function testRouterTwigExtension()
    {
        self::bootKernel();
        $twig = self::$kernel->getContainer()->get('twig');

        $this->assertSame($twig->createTemplate('{{ homepage() }}', null)->render(), '/');
        $this->assertSame($twig->createTemplate('{{ page("homepage") }}', null)->render(), '/');
    }
}
