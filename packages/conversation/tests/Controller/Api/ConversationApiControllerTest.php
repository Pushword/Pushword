<?php

namespace Pushword\Conversation\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class ConversationApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<int> */
    private array $createdIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'conv-api-test-'.uniqid().'@example.com';
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

    #[Override]
    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($this->createdIds as $id) {
            $message = $em->getRepository(Message::class)->find($id);
            if ($message instanceof Message) {
                $em->remove($message);
            }
        }
        /** @var class-string<User> $userClass */
        $userClass = $container->getParameter('pw.entity_user');
        $user = $em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $em->remove($user);
        }
        $em->flush();
        parent::tearDown();
    }

    public function testListRequiresToken(): void
    {
        $this->client->request('GET', '/api/conversation');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateAndGet(): void
    {
        $response = $this->request('POST', '/api/conversation', [
            'content' => 'Hello via API '.uniqid(),
            'authorName' => 'Robin',
            'authorEmail' => 'robin@example.com',
            'host' => 'example.com',
            'referring' => 'https://example.com/post',
            'tags' => ['support'],
        ]);
        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertIsInt($body['id']);
        self::assertSame('Robin', $body['authorName']);
        $this->createdIds[] = $body['id'];

        $this->request('GET', '/api/conversation/'.$body['id']);
        self::assertResponseIsSuccessful();
        $fetched = $this->decode();
        self::assertSame($body['id'], $fetched['id']);
    }

    public function testCreateWithEmptyContentFailsValidation(): void
    {
        $response = $this->request('POST', '/api/conversation', [
            'content' => '',
            'host' => 'example.com',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testPatchUpdatesAuthor(): void
    {
        $id = $this->seed();

        $response = $this->request('PATCH', '/api/conversation/'.$id, ['authorName' => 'Updated']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Updated', $this->decode()['authorName']);
    }

    public function testDeleteRemovesMessage(): void
    {
        $id = $this->seed();
        $response = $this->request('DELETE', '/api/conversation/'.$id);
        self::assertSame(204, $response->getStatusCode());

        $response = $this->request('GET', '/api/conversation/'.$id);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testListFiltersByHost(): void
    {
        $host = 'conv-host-'.uniqid().'.example.com';
        $this->seed(['host' => $host]);

        $this->request('GET', '/api/conversation?host='.$host);
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $this->decode()['total']);
    }

    public function testGetUnknownReturns404(): void
    {
        $response = $this->request('GET', '/api/conversation/9999999');
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seed(array $overrides = []): int
    {
        $payload = array_merge([
            'content' => 'Seed '.uniqid(),
            'host' => 'example.com',
            'authorName' => 'Seed',
        ], $overrides);
        $this->request('POST', '/api/conversation', $payload);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $body = $this->decode();
        self::assertIsInt($body['id']);
        $this->createdIds[] = $body['id'];

        return $body['id'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(string $method, string $url, array $body = []): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken, 'CONTENT_TYPE' => 'application/json'];
        $this->client->request($method, $url, [], [], $server, [] === $body ? '' : (string) json_encode($body));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
