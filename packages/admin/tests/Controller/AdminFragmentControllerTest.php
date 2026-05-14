<?php

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\EventListener\PwAuthCookieListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class AdminFragmentControllerTest extends AbstractAdminTestClass
{
    public function testPageButtonsRequiresAuthAndClearsCookie(): void
    {
        $this->tearDown();
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/admin/fragment/page-buttons/1');

        $response = $client->getResponse();
        self::assertSame(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode(),
            'Unauthenticated fragment requests must return 401 (not redirect to /login)',
        );

        $clearedCookies = array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $c): bool => PwAuthCookieListener::COOKIE_NAME === $c->getName(),
        );
        self::assertNotEmpty($clearedCookies, 'pw_auth cookie should be cleared on 401');
        $cookie = current($clearedCookies);
        self::assertTrue(
            null === $cookie->getValue() || '' === $cookie->getValue(),
            'pw_auth cookie should be emptied',
        );
    }

    public function testPageButtonsUnauthenticatedPostAlsoReturns401(): void
    {
        $this->tearDown();
        $client = self::createClient();

        $client->request(Request::METHOD_POST, '/admin/fragment/page-buttons/1');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testPageButtonsRendersForEditor(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/fragment/page-buttons/1');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty((string) $client->getResponse()->getContent());
    }
}
