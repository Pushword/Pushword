<?php

namespace Pushword\Snippet\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Snippet\Entity\Snippet;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class SnippetApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<int> */
    private array $createdSnippetIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'snippet-api-test-'.uniqid().'@example.com';
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
        $container = $this->client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($this->createdSnippetIds as $id) {
            $snippet = $em->getRepository(Snippet::class)->find($id);
            if ($snippet instanceof Snippet) {
                $em->remove($snippet);
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

    public function testListWithoutTokenReturns401(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/snippet');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateAndGet(): void
    {
        $host = 'snippet-test-'.uniqid().'.example.com';
        $slug = 'hello-'.uniqid();
        $response = $this->request('POST', '/api/snippet/'.$host, [
            'slug' => $slug,
            'name' => 'Hello',
            'content' => 'Hello world!',
            'tags' => ['greeting'],
        ]);
        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame($host, $body['host']);
        self::assertSame($slug, $body['slug']);
        self::assertSame('Hello world!', $body['content']);
        $this->trackSnippet($host, $slug);

        $this->request('GET', '/api/snippet/'.$host.'/'.$slug);
        self::assertResponseIsSuccessful();
        $fetched = $this->decode();
        self::assertSame('Hello world!', $fetched['content']);
    }

    public function testCreateMissingSlugReturns400(): void
    {
        $host = 'snippet-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/snippet/'.$host, ['name' => 'no slug']);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testCreateDuplicateSlugReturns409(): void
    {
        $host = 'snippet-test-'.uniqid().'.example.com';
        $slug = 'dup-'.uniqid();
        $this->request('POST', '/api/snippet/'.$host, ['slug' => $slug, 'name' => 'First', 'content' => 'a']);
        $this->trackSnippet($host, $slug);

        $response = $this->request('POST', '/api/snippet/'.$host, ['slug' => $slug, 'name' => 'Second', 'content' => 'b']);
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUpdateContentAndDelete(): void
    {
        $host = 'snippet-test-'.uniqid().'.example.com';
        $slug = 'edit-'.uniqid();
        $this->request('POST', '/api/snippet/'.$host, ['slug' => $slug, 'name' => 'Original', 'content' => 'old']);
        $this->trackSnippet($host, $slug);

        $response = $this->request('PUT', '/api/snippet/'.$host.'/'.$slug, ['content' => 'new', 'name' => 'Renamed']);
        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('new', $body['content']);
        self::assertSame('Renamed', $body['name']);

        $response = $this->request('DELETE', '/api/snippet/'.$host.'/'.$slug);
        self::assertSame(204, $response->getStatusCode());

        $response = $this->request('GET', '/api/snippet/'.$host.'/'.$slug);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testListFiltersByHost(): void
    {
        $host = 'snippet-test-'.uniqid().'.example.com';
        $slug = 'listed-'.uniqid();
        $this->request('POST', '/api/snippet/'.$host, ['slug' => $slug, 'content' => 'x']);
        $this->trackSnippet($host, $slug);

        $this->request('GET', '/api/snippet?host='.$host);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertSame(1, $body['total']);
    }

    public function testGetUnknownReturns404(): void
    {
        $response = $this->request('GET', '/api/snippet/nope.example.com/missing');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testCreateInvalidSlugReturnsValidationError(): void
    {
        $host = 'snippet-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/snippet/'.$host, [
            'slug' => 'INVALID UPPER',
            'name' => 'x',
            'content' => 'x',
        ]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function trackSnippet(string $host, string $slug): void
    {
        $snippet = $this->em->getRepository(Snippet::class)->findOneBy([
            'host' => $host,
            'slug' => Snippet::normalizeSlug($slug),
        ]);
        if ($snippet instanceof Snippet && null !== $snippet->id) {
            $this->createdSnippetIds[] = $snippet->id;
        }
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
