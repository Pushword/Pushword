<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Pushword\Core\EventListener\AuthenticatedResponseCacheListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class AuthenticatedResponseCacheListenerTest extends TestCase
{
    public function testAuthenticatedResponseCannotBeStoredByTheBrowser(): void
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('editor', null));

        $response = new Response();
        new AuthenticatedResponseCacheListener($security)(
            $this->event(Request::create('/admin/user'), $response),
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('true', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testAnonymousResponseKeepsItsCachePolicy(): void
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $response = $this->cacheableResponse();
        new AuthenticatedResponseCacheListener($security)(
            $this->event(Request::create('/'), $response),
        );

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    /**
     * Reading the user touches the session, which Symfony forbids on a request
     * declared stateless — the guard must come before that read.
     */
    public function testStatelessRequestIsLeftAlone(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');

        $request = Request::create('/api/pages');
        $request->attributes->set('_stateless', true);

        $response = $this->cacheableResponse();
        new AuthenticatedResponseCacheListener($security)($this->event($request, $response));

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSubRequestIsLeftAlone(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');

        $response = $this->cacheableResponse();
        new AuthenticatedResponseCacheListener($security)(
            $this->event(Request::create('/'), $response, HttpKernelInterface::SUB_REQUEST),
        );

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    private function cacheableResponse(): Response
    {
        return new Response(headers: ['Cache-Control' => 'public, max-age=3600']);
    }

    private function event(
        Request $request,
        Response $response,
        int $type = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        return new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, $response);
    }
}
