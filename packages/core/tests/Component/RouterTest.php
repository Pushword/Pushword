<?php

namespace Pushword\Core\Tests\Component;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Router\PushwordRouteGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RouterTest extends KernelTestCase
{
    public function testRouter(): void
    {
        self::bootKernel();

        $router = new PushwordRouteGenerator(
            self::getContainer()->get('router'),
            self::getContainer()->get(AppPool::class),
        );

        self::assertSame('/', $router->generatePathForHomePage());
        self::assertSame('/', $router->generate('homepage'));
        self::assertSame('/page', $router->generate('page'));
    }

    public function testRouterTwigExtension(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');

        self::assertSame($twig->createTemplate('{{ homepage() }}', null)->render(), '/');
        self::assertSame($twig->createTemplate('{{ page("homepage") }}', null)->render(), '/');
    }
}
