<?php

namespace Pushword\Core\Tests\Component;

use Pushword\Core\Router\PushwordRouteGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class RouterTest extends KernelTestCase
{
    public function testRouter()
    {
        self::bootKernel();

        $router = new PushwordRouteGenerator(
            self::$kernel->getContainer()->get('router'),
            self::$kernel->getContainer()->get(\Pushword\Core\Component\App\AppPool::class),
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
        $twig = self::$kernel->getContainer()->get('test.service_container')->get('twig');

        $this->assertSame($twig->createTemplate('{{ homepage() }}', null)->render(), '/');
        $this->assertSame($twig->createTemplate('{{ page("homepage") }}', null)->render(), '/');
    }
}
