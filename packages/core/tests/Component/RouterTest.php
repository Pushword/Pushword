<?php

namespace Pushword\Core\Tests\Component;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RouterTest extends KernelTestCase
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

    private function requestContext(): RequestContext
    {
        return self::getContainer()->get(RequestContext::class);
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

        self::assertSame('/', $twig->createTemplate('{{ homepage() }}', null)->render());
        self::assertSame('/', $twig->createTemplate('{{ page("homepage") }}', null)->render());
    }

    public function testMayUseCustomPathReturnsFalseForDefaultHost(): void
    {
        self::bootKernel();
        $this->registry()->switchSite('localhost.dev');

        self::assertFalse($this->makeRouter()->mayUseCustomPath('localhost.dev'));
    }

    public function testMayUseCustomPathReturnsFalseWhenDirectAccessOnSameHost(): void
    {
        self::bootKernel();
        // Simulate direct access via real host (pushword_page route, not custom_host)
        $this->requestContext()->setRequestContext('pushword.piedweb.com', 'pushword_page', 'some-page');
        $this->registry()->switchSite('pushword.piedweb.com');

        self::assertFalse($this->makeRouter()->mayUseCustomPath('pushword.piedweb.com'));
    }

    public function testMayUseCustomPathReturnsTrueWhenOnCustomHostRoute(): void
    {
        self::bootKernel();
        // Simulate access via 127.0.0.1/{host}/{slug} (custom_host route)
        $this->requestContext()->setRequestContext('pushword.piedweb.com', 'custom_host_pushword_page', 'some-page');
        $this->registry()->switchSite('pushword.piedweb.com');

        // Even though context host matches, we're on a custom_host route → keep prefixing
        self::assertTrue($this->makeRouter()->mayUseCustomPath('pushword.piedweb.com'));
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

    public function testMayUseCustomPathReturnsFalseWhenNoHostContext(): void
    {
        self::bootKernel();
        // No host set in context, no host passed → false
        $this->requestContext()->setRequestContext('');

        self::assertFalse($this->makeRouter()->mayUseCustomPath());
    }

    public function testMayUseCustomPathReturnsFalseWhenDisabled(): void
    {
        self::bootKernel();
        $router = $this->makeRouter();
        $router->setUseCustomHostPath(false);

        self::assertFalse($router->mayUseCustomPath('pushword.piedweb.com'));
    }

    public function testGenerateUsesSimpleRouteWhenDirectAccessOnSameHost(): void
    {
        self::bootKernel();
        $registry = $this->registry();
        $router = $this->makeRouter();

        // Simulate direct access on real host (not custom_host route)
        $this->requestContext()->setRequestContext('pushword.piedweb.com', 'pushword_page', 'test-page');
        $registry->switchSite('pushword.piedweb.com');
        $page = new Page();
        $page->host = 'pushword.piedweb.com';
        $page->setSlug('test-page');

        $registry->setCurrentPage($page);

        // Should generate /test-page, NOT /pushword.piedweb.com/test-page
        self::assertSame('/test-page', $router->generate($page));
    }

    public function testGenerateKeepsCustomHostPrefixWhenOnCustomHostRoute(): void
    {
        self::bootKernel();
        $registry = $this->registry();
        $router = $this->makeRouter();

        // Simulate access via 127.0.0.1/pushword.piedweb.com/test-page
        $this->requestContext()->setRequestContext('pushword.piedweb.com', 'custom_host_pushword_page', 'test-page');
        $registry->switchSite('pushword.piedweb.com');
        $page = new Page();
        $page->host = 'pushword.piedweb.com';
        $page->setSlug('other-page');

        $registry->setCurrentPage($page);

        // Should keep /pushword.piedweb.com/other-page prefix
        self::assertSame('/pushword.piedweb.com/other-page', $router->generate($page));
    }
}
