<?php

namespace Pushword\Core\Tests\EventListener;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\EventListener\PwAuthCookieListener;
use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the "authenticated ⇒ pw_auth=1" heal performed on kernel.response.
 *
 * loginUser() sets the token straight into the session without firing
 * LoginSuccessEvent — exactly the state a remember-me / restored session leaves:
 * authenticated, no pw_auth cookie. That reproduces the toolbar-missing bug.
 */
#[Group('integration')]
final class PwAuthCookieHealListenerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testHealsAuthenticatedRequestMissingCookie(): void
    {
        $this->client->loginUser($this->getOrCreateUser('pw-auth-heal@example.tld'));

        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');

        $cookie = $this->pwAuthResponseCookie();
        self::assertNotNull($cookie, 'pw_auth=1 must be re-set for an authenticated request missing the cookie');
        self::assertSame('1', $cookie->getValue());
        self::assertSame('/', $cookie->getPath());
        self::assertFalse($cookie->isHttpOnly(), 'Cookie must be readable by JS');
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testDoesNotReHealWhenCookieAlreadyPresent(): void
    {
        $this->client->loginUser($this->getOrCreateUser('pw-auth-heal@example.tld'));

        // First request heals; the BrowserKit cookie jar now carries pw_auth=1.
        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');
        self::assertNotNull($this->pwAuthResponseCookie(), 'sanity: the first request heals');

        // Second request already carries pw_auth=1 → no redundant Set-Cookie.
        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');
        self::assertNull($this->pwAuthResponseCookie(), 'No redundant Set-Cookie when pw_auth is already present');
    }

    public function testDoesNotSetCookieForAnonymousRequest(): void
    {
        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->pwAuthResponseCookie(), 'Anonymous requests must not receive a pw_auth cookie');
    }

    public function testDoesNotHealAuthenticatedNonEditor(): void
    {
        // A downstream front-office session (customer account): authenticated, no
        // ROLE_EDITOR. The admin-toolbar hint must not be set for it.
        $this->client->loginUser($this->getOrCreateUser('pw-auth-customer@example.tld', []));

        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');

        self::assertNull(
            $this->pwAuthResponseCookie(),
            'Authenticated non-editor requests must not receive a pw_auth cookie',
        );
    }

    private function pwAuthResponseCookie(): ?Cookie
    {
        $cookies = array_filter(
            $this->client->getResponse()->headers->getCookies(),
            static fn (Cookie $c): bool => PwAuthCookieListener::COOKIE_NAME === $c->getName(),
        );

        return [] === $cookies ? null : current($cookies);
    }

    /** @param string[] $roles */
    private function getOrCreateUser(string $email, array $roles = [User::ROLE_SUPER_ADMIN]): User
    {
        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);

        $existing = $userRepo->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $email;
        $user->setRoles($roles);

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
