<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\RequestContext;
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

    public function testNativeObfuscatedMarkdownLinksExcludeLiveAdmins(): void
    {
        $security = self::createStub(Security::class);
        $security->method('isGranted')->willReturn(true);
        $request = Request::create('http://example.com/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $stack = new RequestStack([$request]);

        $site = $this->buildSiteRegistry();
        $provider = $this->buildProvider($security, $stack, $site);
        self::assertFalse($provider->canRenderObfuscatedMarkdownLinkNatively());

        $site->get()->setStatic(true);
        self::assertTrue($provider->canRenderObfuscatedMarkdownLinkNatively());

        $customSite = $this->buildSiteRegistry('@Custom');
        $customSite->get()->setStatic(true);
        self::assertTrue($this->buildProvider($security, $stack, $customSite)->canRenderObfuscatedMarkdownLinkNatively());
    }

    public function testContactMarkupUsesOnlyCoreTemplates(): void
    {
        $rendered = [];
        $twig = self::createStub(Twig::class);
        $twig->method('render')->willReturnCallback(static function (string $template, array $context) use (&$rendered): string {
            $rendered[] = [$template, $context];

            return '<span>contact</span>';
        });
        $provider = $this->buildProvider(self::createStub(Security::class), new RequestStack(), $this->buildSiteRegistry('@Custom'), $twig);

        $provider->renderLink('Contact', '/contact');
        $provider->renderEncodedMail('contact@example.com', 'mail-style');
        $provider->renderPhoneNumber('01 23 45 67 89', 'phone-style');

        self::assertSame([
            '@Pushword/component/link_js.html.twig',
            '@Pushword/component/encoded_mail.html.twig',
            '@Pushword/component/phone_number.html.twig',
        ], array_column($rendered, 0));
        self::assertSame('mail-style', $rendered[1][1]['class']);
        self::assertSame('phone-style', $rendered[2][1]['class']);
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
                '@Pushword/component/link_js.html.twig',
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

    private function buildSiteRegistry(string $template = '@Pushword'): SiteRegistry
    {
        $sites = new SiteRegistry(
            ['example.com' => [
                'hosts' => ['example.com'],
                'locale' => 'en',
                'template' => $template,
                'template_dir' => \dirname(__DIR__, 2).'/src/templates',
            ]],
            new TemplateResolver(self::createStub(Twig::class), new ArrayAdapter()),
            new ParameterBag(),
        );
        $sites->setRequestContext(new RequestContext($sites));

        return $sites;
    }
}
