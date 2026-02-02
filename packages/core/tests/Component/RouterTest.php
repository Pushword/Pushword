<?php

namespace Pushword\Core\Tests\Component;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class RouterTest extends KernelTestCase
{
    public function testRouter(): void
    {
        self::bootKernel();

        $router = new PushwordRouteGenerator(
            self::getContainer()->get('router'),
            self::getContainer()->get(SiteRegistry::class),
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
