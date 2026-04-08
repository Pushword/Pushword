<?php

namespace Pushword\Core\Tests\Component;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class RouterTest extends KernelTestCase
{
    private function makeRouter(): PushwordRouteGenerator
    {
        return new PushwordRouteGenerator(
            self::getContainer()->get('router'),
            self::getContainer()->get(SiteRegistry::class),
        );
    }

    private function registry(): SiteRegistry
    {
        return self::getContainer()->get(SiteRegistry::class);
    }

    public function testRouter(): void
    {
        self::bootKernel();
        $router = $this->makeRouter();

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

    public function testMayUseCustomPathReturnsFalseForDefaultHost(): void
    {
        self::bootKernel();
        $this->registry()->switchSite('localhost.dev');

        self::assertFalse($this->makeRouter()->mayUseCustomPath('localhost.dev'));
    }

    public function testMayUseCustomPathReturnsFalseWhenHostMatchesCurrent(): void
    {
        self::bootKernel();
        $this->registry()->switchSite('pushword.piedweb.com');

        // Generating a link for same host should NOT use custom_host route
        self::assertFalse($this->makeRouter()->mayUseCustomPath('pushword.piedweb.com'));
    }

    public function testMayUseCustomPathReturnsTrueForDifferentNonDefaultHost(): void
    {
        self::bootKernel();
        $registry = $this->registry();
        $registry->switchSite('localhost.dev');

        $page = new Page();
        $page->host = 'pushword.piedweb.com';
        $registry->setCurrentPage($page);

        self::assertTrue($this->makeRouter()->mayUseCustomPath('pushword.piedweb.com'));
    }

    public function testMayUseCustomPathReturnsFalseWhenDisabled(): void
    {
        self::bootKernel();
        $router = $this->makeRouter();
        $router->setUseCustomHostPath(false);

        self::assertFalse($router->mayUseCustomPath('pushword.piedweb.com'));
    }

    public function testGenerateUsesSimpleRouteWhenOnSameNonDefaultHost(): void
    {
        self::bootKernel();
        $registry = $this->registry();
        $router = $this->makeRouter();

        $registry->switchSite('pushword.piedweb.com');
        $page = new Page();
        $page->host = 'pushword.piedweb.com';
        $page->setSlug('test-page');
        $registry->setCurrentPage($page);

        // Should generate /test-page, NOT /pushword.piedweb.com/test-page
        self::assertSame('/test-page', $router->generate($page));
    }
}
