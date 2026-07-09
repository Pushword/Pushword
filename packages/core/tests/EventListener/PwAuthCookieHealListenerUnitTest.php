<?php

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Pushword\Core\EventListener\PwAuthCookieHealListener;
use Pushword\Core\EventListener\PwAuthCookieListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Branch-level coverage for the guards that the functional
 * {@see PwAuthCookieHealListenerTest} cannot reach: sub-requests never occur
 * through WebTestCase, and the stateless (API) path would need cross-package
 * token auth. The happy-path anchor proves the negative cases skip because of a
 * guard, not because the setup fails to produce a cookie.
 */
final class PwAuthCookieHealListenerUnitTest extends TestCase
{
    public function testSetsCookieForAuthenticatedMainRequestWithoutCookie(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('u', null));

        $response = new Response();
        new PwAuthCookieHealListener($security)->onKernelResponse(
            $this->event(Request::create('https://example.com/'), $response),
        );

        self::assertNotNull($this->pwAuthCookie($response));
    }

    public function testSkipsSubRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');

        $response = new Response();
        new PwAuthCookieHealListener($security)->onKernelResponse(
            $this->event(Request::create('https://example.com/'), $response, HttpKernelInterface::SUB_REQUEST),
        );

        self::assertNull($this->pwAuthCookie($response));
    }

    public function testSkipsStatelessRequest(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');

        $request = Request::create('https://example.com/api/pages');
        $request->attributes->set('_stateless', true);

        $response = new Response();
        new PwAuthCookieHealListener($security)->onKernelResponse($this->event($request, $response));

        self::assertNull($this->pwAuthCookie($response));
    }

    private function event(
        Request $request,
        Response $response,
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        return new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, $response);
    }

    private function pwAuthCookie(Response $response): ?Cookie
    {
        $cookies = array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $c): bool => PwAuthCookieListener::COOKIE_NAME === $c->getName(),
        );

        return [] === $cookies ? null : current($cookies);
    }
}
