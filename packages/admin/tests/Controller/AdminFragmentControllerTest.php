<?php

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\User;
use Pushword\Core\EventListener\PwAuthCookieListener;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
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

    public function testPageButtonsForbiddenForNonEditorAndClearsCookie(): void
    {
        $this->tearDown();
        $client = self::createClient();

        // A downstream front-office session (customer account) carrying a stale
        // pw_auth cookie: authenticated, no ROLE_EDITOR.
        $client->loginUser($this->getOrCreateNonEditorUser());
        $client->getCookieJar()->set(
            new BrowserKitCookie(PwAuthCookieListener::COOKIE_NAME, '1'),
        );

        $client->request(Request::METHOD_POST, '/admin/fragment/page-buttons/1');

        $response = $client->getResponse();
        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $response->getStatusCode(),
            'Authenticated non-editors must get 403, not the fragment',
        );
        self::assertEmpty((string) $response->getContent());

        $clearedCookies = array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $c): bool => PwAuthCookieListener::COOKIE_NAME === $c->getName(),
        );
        self::assertNotEmpty($clearedCookies, 'The stale pw_auth cookie should be cleared on 403');
        $cookie = current($clearedCookies);
        self::assertTrue(
            null === $cookie->getValue() || '' === $cookie->getValue(),
            'pw_auth cookie should be emptied',
        );
    }

    public function testPageButtonsRendersForEditor(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/fragment/page-buttons/1');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty((string) $client->getResponse()->getContent());
    }

    public function testPageButtonsUnknownPageReturns404ForEditor(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/fragment/page-buttons/99999999');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function getOrCreateNonEditorUser(): User
    {
        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);

        $existing = $userRepo->findOneBy(['email' => 'fragment-customer@example.tld']);
        if ($existing instanceof User) {
            return $existing;
        }

        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = 'fragment-customer@example.tld';
        $user->setRoles([]);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * The "list" button must reproduce the applied-filter format EasyAdmin expects
     * (plural `filters`, a `comparison`, and a scalar `value`) — the same one the
     * admin host menu emits. The former singular/array format silently filtered
     * nothing. Page 1 lives on host "localhost.dev".
     */
    public function testPageButtonsListLinkUsesWorkingHostFilterFormat(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/fragment/page-buttons/1');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('filters%5Bhost%5D%5Bcomparison%5D=%3D', $content);
        self::assertStringContainsString('filters%5Bhost%5D%5Bvalue%5D=localhost.dev', $content);
        self::assertStringNotContainsString('filter%5Bhost%5D%5Bvalue%5D%5B%5D', $content);
    }
}
