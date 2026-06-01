<?php

namespace Pushword\Conversation\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class ReviewApiControllerTest extends WebTestCase
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
        $this->testUserEmail = 'review-api-test-'.uniqid().'@example.com';
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
            $review = $em->getRepository(Review::class)->find($id);
            if ($review instanceof Review) {
                $em->remove($review);
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
        $this->client->request('GET', '/api/review');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateAndGet(): void
    {
        $response = $this->request('POST', '/api/review', [
            'content' => 'Great product '.uniqid(),
            'title' => 'Loved it',
            'rating' => 5,
            'authorName' => 'Robin',
            'host' => 'example.com',
        ]);
        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertIsInt($body['id']);
        self::assertSame('Loved it', $body['title']);
        self::assertSame(5, $body['rating']);
        $this->createdIds[] = $body['id'];

        $this->request('GET', '/api/review/'.$body['id']);
        self::assertResponseIsSuccessful();
    }

    public function testPatchRating(): void
    {
        $id = $this->seed();
        $response = $this->request('PATCH', '/api/review/'.$id, ['rating' => 3]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $this->decode()['rating']);
    }

    public function testDelete(): void
    {
        $id = $this->seed();
        $response = $this->request('DELETE', '/api/review/'.$id);
        self::assertSame(204, $response->getStatusCode());
    }

    public function testListFiltersByHost(): void
    {
        $host = 'review-host-'.uniqid().'.example.com';
        $this->seed(['host' => $host]);
        $this->request('GET', '/api/review?host='.$host);
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $this->decode()['total']);
    }

    public function testEmptyContentFailsValidation(): void
    {
        $response = $this->request('POST', '/api/review', ['content' => '', 'host' => 'example.com']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seed(array $overrides = []): int
    {
        $payload = array_merge([
            'content' => 'Seed '.uniqid(),
            'title' => 'Title',
            'rating' => 4,
            'host' => 'example.com',
        ], $overrides);
        $this->request('POST', '/api/review', $payload);
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
