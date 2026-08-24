<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;

#[AllowMockObjectsWithoutExpectations]
final class LinkProviderTest extends TestCase
{
    public function testCurrentUserIsAdminReturnsFalseWithoutAnyRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');

        $provider = $this->buildProvider($security, new RequestStack());

        self::assertFalse($this->invokeCurrentUserIsAdmin($provider));
    }

    public function testCurrentUserIsAdminReturnsFalseWhenRequestHasNoSession(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');

        $stack = new RequestStack([Request::create('http://example.com/')]);

        $provider = $this->buildProvider($security, $stack);

        self::assertFalse($this->invokeCurrentUserIsAdmin($provider));
    }

    public function testCurrentUserIsAdminDelegatesToSecurityWhenSessionAvailable(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $request = Request::create('http://example.com/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $stack = new RequestStack([$request]);

        $provider = $this->buildProvider($security, $stack);

        self::assertTrue($this->invokeCurrentUserIsAdmin($provider));
    }

    public function testCurrentUserIsAdminReturnsFalseForNonAdminWithSession(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $request = Request::create('http://example.com/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $stack = new RequestStack([$request]);

        $provider = $this->buildProvider($security, $stack);

        self::assertFalse($this->invokeCurrentUserIsAdmin($provider));
    }

    #[DataProvider('provideObfuscationDebugTitleCases')]
    public function testObfuscationDebugTitleIsLimitedToLiveAdminRequests(
        bool $isStatic,
        ?string $expectedTitle,
    ): void {
        $security = self::createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $request = Request::create('http://example.com/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $siteRegistry = $this->buildSiteRegistry();
        $siteRegistry->get()->setStatic($isStatic);

        $twig = $this->createMock(Twig::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '/component/link_js.html.twig',
                self::callback(static function (array $context) use ($expectedTitle): bool {
                    self::assertArrayHasKey('attr', $context);
                    self::assertIsArray($context['attr']);
                    self::assertSame($expectedTitle, $context['attr']['title'] ?? null);

                    return true;
                }),
            )
            ->willReturn('<span>Example</span>');

        $provider = $this->buildProvider(
            $security,
            new RequestStack([$request]),
            $siteRegistry,
            $twig,
        );

        $provider->renderLink('Example', 'https://example.com/');
    }

    /** @return iterable<string, array{bool, ?string}> */
    public static function provideObfuscationDebugTitleCases(): iterable
    {
        yield 'static export' => [true, null];
        yield 'live admin request' => [false, 'obf'];
    }

    private function buildProvider(
        Security $security,
        RequestStack $requestStack,
        ?SiteRegistry $siteRegistry = null,
        ?Twig $twig = null,
    ): LinkProvider {
        $siteRegistry ??= $this->buildSiteRegistry();

        return new LinkProvider(
            new PushwordRouteGenerator(self::createStub(RouterInterface::class), $siteRegistry),
            $siteRegistry,
            $twig ?? self::createStub(Twig::class),
            $security,
            $requestStack,
        );
    }

    private function invokeCurrentUserIsAdmin(LinkProvider $provider): bool
    {
        $method = new ReflectionMethod($provider, 'currentUserIsAdmin');

        return (bool) $method->invoke($provider);
    }

    private function buildSiteRegistry(): SiteRegistry
    {
        return new SiteRegistry(
            ['example.com' => [
                'hosts' => ['example.com'],
                'locale' => 'en',
                'template' => '@Pushword',
                'template_dir' => \dirname(__DIR__, 2).'/src/templates',
            ]],
            new TemplateResolver(self::createStub(Twig::class), new ArrayAdapter()),
            new ParameterBag(),
        );
    }
}
