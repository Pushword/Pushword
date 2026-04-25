<?php

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Pushword\Core\EventListener\PwAuthCookieListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class PwAuthCookieListenerTest extends TestCase
{
    private PwAuthCookieListener $listener;

    protected function setUp(): void
    {
        $this->listener = new PwAuthCookieListener();
    }

    public function testLoginSuccessSetsPwAuthCookie(): void
    {
        $request = Request::create('https://example.com/login');
        $response = new Response();

        $event = $this->createLoginSuccessEvent($request, $response);
        $this->listener->onLoginSuccess($event);

        $cookies = $response->headers->getCookies();
        $names = array_map(static fn (Cookie $c): string => $c->getName(), $cookies);
        self::assertContains('pw_auth', $names, 'pw_auth cookie should be set on login');

        $cookie = current(array_filter($cookies, static fn (Cookie $c): bool => 'pw_auth' === $c->getName()));
        self::assertNotFalse($cookie);
        self::assertSame('1', $cookie->getValue());
        self::assertFalse($cookie->isHttpOnly(), 'Cookie must be readable by JS');
        self::assertSame('/', $cookie->getPath());
    }

    public function testLoginSuccessNoopWhenNoResponse(): void
    {
        $request = Request::create('https://example.com/login');
        $event = $this->createLoginSuccessEvent($request, null);

        // Should not throw even when response is null
        $this->listener->onLoginSuccess($event);
        $this->addToAssertionCount(1);
    }

    public function testLogoutClearsCookie(): void
    {
        $request = Request::create('https://example.com/logout');
        $response = new Response();

        $event = new LogoutEvent($request, null);
        $event->setResponse($response);

        $this->listener->onLogout($event);

        $cookies = $response->headers->getCookies();
        $pwAuthCookies = array_filter($cookies, static fn (Cookie $c): bool => 'pw_auth' === $c->getName());

        self::assertNotEmpty($pwAuthCookies, 'Clearing the cookie should produce a header');

        $clearCookie = current($pwAuthCookies);
        // A cleared cookie has expiry in the past or value ''
        self::assertTrue(
            '' === $clearCookie->getValue() || $clearCookie->getExpiresTime() < time(),
            'Cookie should be cleared (empty value or past expiry)',
        );
    }

    public function testLogoutNoopWhenNoResponse(): void
    {
        $request = Request::create('https://example.com/logout');
        $event = new LogoutEvent($request, null);

        // No response set — should not throw
        $this->listener->onLogout($event);
        $this->addToAssertionCount(1);
    }

    private function createLoginSuccessEvent(Request $request, ?Response $response): LoginSuccessEvent
    {
        $user = new InMemoryUser('admin', null);
        $passport = new Passport(
            new UserBadge('admin', static fn (): InMemoryUser => $user),
            new PasswordCredentials(''),
        );
        $token = new UsernamePasswordToken($user, 'main');

        return new LoginSuccessEvent(
            self::createStub(AuthenticatorInterface::class),
            $passport,
            $token,
            $request,
            $response,
            'main',
        );
    }
}
