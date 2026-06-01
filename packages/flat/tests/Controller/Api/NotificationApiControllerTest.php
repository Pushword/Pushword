<?php

namespace Pushword\Flat\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Flat\Entity\AdminNotification;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class NotificationApiControllerTest extends WebTestCase
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
        $this->testUserEmail = 'notif-api-test-'.uniqid().'@example.com';
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
            $notification = $em->getRepository(AdminNotification::class)->find($id);
            if ($notification instanceof AdminNotification) {
                $em->remove($notification);
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
        $this->client->request('GET', '/api/notification');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testListReturnsSeededNotification(): void
    {
        $host = 'notif-host-'.uniqid().'.example.com';
        $this->seed($host);

        $this->request('GET', '/api/notification?host='.$host);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertSame(1, $body['total']);
    }

    public function testGetReturnsItem(): void
    {
        $id = $this->seed();
        $this->request('GET', '/api/notification/'.$id);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertSame($id, $body['id']);
        self::assertFalse($body['isRead']);
    }

    public function testMarkAsRead(): void
    {
        $id = $this->seed();
        $response = $this->request('POST', '/api/notification/'.$id.'/read');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode();
        self::assertTrue($body['isRead']);
    }

    public function testDelete(): void
    {
        $id = $this->seed();
        $response = $this->request('DELETE', '/api/notification/'.$id);
        self::assertSame(204, $response->getStatusCode());

        $response = $this->request('GET', '/api/notification/'.$id);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testUnreadFilter(): void
    {
        $host = 'notif-host-'.uniqid().'.example.com';
        $unreadId = $this->seed($host);
        $readId = $this->seed($host);

        // Mark one as read.
        $this->request('POST', '/api/notification/'.$readId.'/read');

        $this->request('GET', '/api/notification?host='.$host.'&unread=1');
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertSame(1, $body['total']);
        self::assertIsArray($body['items']);
        $firstItem = $body['items'][0] ?? null;
        self::assertIsArray($firstItem);
        self::assertSame($unreadId, $firstItem['id']);
    }

    public function testGetUnknownReturns404(): void
    {
        $response = $this->request('GET', '/api/notification/99999999');
        self::assertSame(404, $response->getStatusCode());
    }

    private function seed(?string $host = null): int
    {
        $notification = new AdminNotification();
        $notification->type = 'info';
        $notification->message = 'Test '.uniqid();
        $notification->host = $host;
        $this->em->persist($notification);
        $this->em->flush();
        $this->createdIds[] = $notification->id ?? 0;

        return $notification->id ?? 0;
    }

    private function request(string $method, string $url): Response
    {
        $this->client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ]);

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
