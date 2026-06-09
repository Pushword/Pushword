<?php

namespace Pushword\Api\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class WhoAmIApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'whoami-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        /** @var class-string<User> $userClass */
        $userClass = $this->client->getContainer()->getParameter('pw.entity_user');
        $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $this->em->remove($user);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testWhoAmIReturnsAuthenticatedUser(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/whoami', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->client->getResponse()->getContent();
        self::assertIsString($body);

        $payload = json_decode($body, true);
        self::assertIsArray($payload);
        self::assertSame($this->testUserEmail, $payload['email'] ?? null);
        self::assertSame($this->testUserEmail, $payload['username'] ?? null);
        $roles = $payload['roles'] ?? [];
        self::assertIsArray($roles);
        self::assertContains('ROLE_EDITOR', $roles);
    }

    public function testWhoAmIRejectsInvalidToken(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/whoami', server: [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }
}
