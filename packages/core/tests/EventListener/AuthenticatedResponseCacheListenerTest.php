<?php

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
        $event = new ResponseEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create('/admin/user'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        new AuthenticatedResponseCacheListener($security)($event);

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('true', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testAnonymousResponseKeepsItsCachePolicy(): void
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $response = new Response(headers: ['Cache-Control' => 'public, max-age=3600']);
        $event = new ResponseEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        new AuthenticatedResponseCacheListener($security)($event);

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }
}
