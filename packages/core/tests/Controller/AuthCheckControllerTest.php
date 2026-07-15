<?php

namespace Pushword\Core\Tests\Controller;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class AuthCheckControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testAnonymousVisitorGets401(): void
    {
        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        // The endpoint exposes per-visitor auth state, so neither the 401 nor the
        // 204 may ever be stored by a shared cache.
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testAuthenticatedVisitorGets204(): void
    {
        $user = $this->getOrCreateUser('auth-check-user@example.tld');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/_pushword/auth-check');

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        // Exact header: the controller opts out of Symfony's session auto
        // cache-control (AbstractSessionListener), which would otherwise prepend
        // "max-age=0, must-revalidate, private" once the session is read to resolve
        // auth (and again by PwAuthCookieHealListener). Guards that opt-out.
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    private function getOrCreateUser(string $email): User
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
        $user->setRoles([User::ROLE_SUPER_ADMIN]);

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
