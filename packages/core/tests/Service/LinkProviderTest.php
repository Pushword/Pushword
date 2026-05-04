<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\LinkProvider;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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

        $stack = new RequestStack();
        $stack->push(Request::create('http://example.com/'));

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

        $stack = new RequestStack();
        $stack->push($request);

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

        $stack = new RequestStack();
        $stack->push($request);

        $provider = $this->buildProvider($security, $stack);

        self::assertFalse($this->invokeCurrentUserIsAdmin($provider));
    }

    private function buildProvider(Security $security, RequestStack $requestStack): LinkProvider
    {
        $reflection = new ReflectionClass(LinkProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('security')->setValue($provider, $security);
        $reflection->getProperty('requestStack')->setValue($provider, $requestStack);

        return $provider;
    }

    private function invokeCurrentUserIsAdmin(LinkProvider $provider): bool
    {
        $method = new ReflectionMethod($provider, 'currentUserIsAdmin');

        return (bool) $method->invoke($provider);
    }
}
