<?php

namespace Pushword\Core\Tests\Controller;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
class UserControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testLoginPageShowsEmailStep(): void
    {
        $this->client->request(Request::METHOD_GET, '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"]');
    }

    public function testCheckEmailWithUnknownUser(): void
    {
        $this->client->request(Request::METHOD_GET, '/login');
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form();
        $form['email'] = 'unknown-user@example.tld';
        $this->client->submit($form);

        self::assertResponseRedirects('/login');
    }

    public function testCheckEmailWithUserHavingPassword(): void
    {
        $this->createTestUser('password-user@example.tld', 'testPassword123');

        $this->client->request(Request::METHOD_GET, '/login');
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form();
        $form['email'] = 'password-user@example.tld';
        $this->client->submit($form);

        self::assertResponseRedirects('/login?step=password');
        $crawler = $this->client->followRedirect();

        // Should now show password field
        self::assertSelectorExists('input[name="password"]');
    }

    public function testCheckEmailWithUserWithoutPassword(): void
    {
        $this->createTestUser('no-password-user@example.tld', null);

        $this->client->request(Request::METHOD_GET, '/login');
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form();
        $form['email'] = 'no-password-user@example.tld';
        $this->client->submit($form);

        // Should show magic link sent page
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-green-100');
    }

    public function testMagicLoginWithInvalidToken(): void
    {
        $this->client->request(Request::METHOD_GET, '/login/magic/invalid-token');

        self::assertResponseRedirects('/login');
    }

    public function testMagicLoginWithValidToken(): void
    {
        $user = $this->createTestUser('magic-login-user@example.tld', null);
        $plainToken = bin2hex(random_bytes(32));
        $this->createLoginToken($user, $plainToken, LoginToken::TYPE_LOGIN);

        $urlToken = base64_encode($user->getId().':'.$plainToken);
        $this->client->request(Request::METHOD_GET, '/login/magic/'.$urlToken);

        // Should redirect to admin after successful login
        self::assertResponseRedirects();
    }

    public function testSetPasswordPageWithValidToken(): void
    {
        $user = $this->createTestUser('set-password-user@example.tld', null);
        $plainToken = bin2hex(random_bytes(32));
        $this->createLoginToken($user, $plainToken, LoginToken::TYPE_SET_PASSWORD);

        $urlToken = base64_encode($user->getId().':'.$plainToken);
        $this->client->request(Request::METHOD_GET, '/login/set-password/'.$urlToken);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="password"]');
        self::assertSelectorExists('input[name="password_confirm"]');
    }

    public function testSetPasswordWithMismatchedPasswords(): void
    {
        $user = $this->createTestUser('mismatch-user@example.tld', null);
        $plainToken = bin2hex(random_bytes(32));
        $this->createLoginToken($user, $plainToken, LoginToken::TYPE_SET_PASSWORD);

        $urlToken = base64_encode($user->getId().':'.$plainToken);
        $this->client->request(Request::METHOD_GET, '/login/set-password/'.$urlToken);

        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form();
        $form['password'] = 'password123';
        $form['password_confirm'] = 'different456';
        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-red-200');
    }

    public function testSetPasswordWithTooShortPassword(): void
    {
        $user = $this->createTestUser('short-password-user@example.tld', null);
        $plainToken = bin2hex(random_bytes(32));
        $this->createLoginToken($user, $plainToken, LoginToken::TYPE_SET_PASSWORD);

        $urlToken = base64_encode($user->getId().':'.$plainToken);
        $this->client->request(Request::METHOD_GET, '/login/set-password/'.$urlToken);

        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form();
        $form['password'] = 'short';
        $form['password_confirm'] = 'short';
        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.bg-red-200');
    }

    public function testSetPasswordSuccessfully(): void
    {
        $user = $this->createTestUser('success-user@example.tld', null);
        $plainToken = bin2hex(random_bytes(32));
        $this->createLoginToken($user, $plainToken, LoginToken::TYPE_SET_PASSWORD);

        $urlToken = base64_encode($user->getId().':'.$plainToken);
        $this->client->request(Request::METHOD_GET, '/login/set-password/'.$urlToken);

        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->form();
        $form['password'] = 'newPassword123';
        $form['password_confirm'] = 'newPassword123';
        $this->client->submit($form);

        self::assertResponseRedirects();
    }

    public function testLoginRedirectsWhenAlreadyLoggedIn(): void
    {
        $user = $this->createTestUser('logged-in-user@example.tld', 'testPassword');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/login');

        self::assertResponseRedirects();
    }

    private function createTestUser(string $email, ?string $password): User
    {
        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);

        // Check if user already exists
        $existingUser = $userRepo->findOneBy(['email' => $email]);
        if ($existingUser instanceof User) {
            return $existingUser;
        }

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $user = new User();
        $user->email = $email;
        $user->setRoles([User::ROLE_SUPER_ADMIN]);

        if (null !== $password) {
            $user->setPlainPassword($password);
        }

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createLoginToken(User $user, string $plainToken, string $type): LoginToken
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $token = new LoginToken($user, $type);
        $token->setToken($plainToken);

        $em->persist($token);
        $em->flush();

        return $token;
    }
}
