<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\EventListener\AdminNoindexListener;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AdminNoindexListenerTest extends WebTestCase
{
    /**
     * An anonymous crawler never reaches an admin screen: the firewall answers
     * with a redirect to /login, and that redirect must carry the header too.
     */
    #[Group('integration')]
    public function testAnonymousAdminRedirectIsNoindexNofollow(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/admin');

        self::assertResponseRedirects();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAdminPaths(): iterable
    {
        yield 'dashboard' => ['/admin'];
        yield 'dashboard with trailing slash' => ['/admin/'];
        yield 'dashboard with query string' => ['/admin?query=seo'];
        yield 'crud screen' => ['/admin/page/12/edit'];
        yield 'extension screen' => ['/admin/repurpose/studio/3'];
    }

    #[DataProvider('provideAdminPaths')]
    public function testAdminResponseIsNoindexNofollow(string $path): void
    {
        $response = $this->dispatch(Request::create($path));

        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePublicPaths(): iterable
    {
        yield 'homepage' => ['/'];
        yield 'slug starting with admin' => ['/administration'];
        yield 'login' => ['/login'];
    }

    #[DataProvider('providePublicPaths')]
    public function testPublicResponseIsLeftAlone(string $path): void
    {
        $response = $this->dispatch(Request::create($path));

        self::assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function testSubRequestIsLeftAlone(): void
    {
        $response = $this->dispatch(Request::create('/admin/page'), HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('X-Robots-Tag'));
    }

    private function dispatch(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response = new Response();
        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, $response);
        (new AdminNoindexListener())($event);

        return $response;
    }
}
