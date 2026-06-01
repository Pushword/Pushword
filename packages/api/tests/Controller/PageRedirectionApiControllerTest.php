<?php

namespace Pushword\Api\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PageRedirectionApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<int> */
    private array $createdPageIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'redirection-api-test-'.uniqid().'@example.com';
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
        foreach ($this->createdPageIds as $id) {
            $page = $em->getRepository(Page::class)->find($id);
            if ($page instanceof Page) {
                $em->remove($page);
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

    public function testCreateRedirectionAndFetchBack(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $slug = 'old-'.uniqid();
        $response = $this->request('POST', '/api/redirection/'.$host, [
            'slug' => $slug,
            'redirectTo' => 'https://new.example.com/here',
            'code' => 302,
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame($host, $body['host']);
        self::assertSame($slug, $body['slug']);
        self::assertSame('https://new.example.com/here', $body['redirectTo']);
        self::assertSame(302, $body['code']);
        self::assertIsString($body['revision']);
        $this->createdPageIds[] = $this->lookupPageId($host, $body['slug']);

        // GET round-trip
        $this->request('GET', '/api/redirection/'.$host.'/'.$slug);
        self::assertResponseIsSuccessful();
        $fetched = $this->decode();
        self::assertSame($slug, $fetched['slug']);
    }

    public function testCreateMissingTargetReturns400(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $response = $this->request('POST', '/api/redirection/'.$host, ['slug' => 'old']);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testUpdateRequiresIfMatch(): void
    {
        [$host, $slug] = $this->seedRedirection();
        $response = $this->request('PUT', '/api/redirection/'.$host.'/'.$slug, ['redirectTo' => 'https://x/']);
        self::assertSame(Response::HTTP_PRECONDITION_REQUIRED, $response->getStatusCode());
    }

    public function testUpdateWithMatchingIfMatchReplacesTarget(): void
    {
        [$host, $slug] = $this->seedRedirection();
        $this->request('GET', '/api/redirection/'.$host.'/'.$slug);
        $current = $this->decode();
        self::assertIsString($current['revision']);

        $response = $this->request('PUT', '/api/redirection/'.$host.'/'.$slug, [
            'redirectTo' => 'https://updated.example.com/',
            'code' => 301,
        ], ['HTTP_IF_MATCH' => $current['revision']]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('https://updated.example.com/', $body['redirectTo']);
        self::assertSame(301, $body['code']);
    }

    public function testDeleteRemovesPage(): void
    {
        [$host, $slug] = $this->seedRedirection();
        $response = $this->request('DELETE', '/api/redirection/'.$host.'/'.$slug);
        self::assertSame(204, $response->getStatusCode());

        $response = $this->request('GET', '/api/redirection/'.$host.'/'.$slug);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetOnRegularPageReturns404(): void
    {
        // A page without "Location:" prefix should be invisible to the redirection endpoint.
        $host = 'api-test-'.uniqid().'.example.com';
        $page = new Page();
        $page->host = $host;
        $page->setSlug('regular-page');
        $page->setMainContent('# Just content');
        $this->em->persist($page);
        $this->em->flush();
        $this->createdPageIds[] = $page->id ?? 0;

        $response = $this->request('GET', '/api/redirection/'.$host.'/regular-page');
        self::assertSame(404, $response->getStatusCode());
    }

    public function testListReturnsOnlyRedirections(): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        [, $slug] = $this->seedRedirection($host);

        $response = $this->request('GET', '/api/redirection?host='.$host);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertGreaterThan(0, $body['total']);
        self::assertIsArray($body['items']);
        $slugs = [];
        foreach ($body['items'] as $row) {
            if (\is_array($row) && isset($row['slug']) && \is_string($row['slug'])) {
                $slugs[] = $row['slug'];
            }
        }
        self::assertContains($slug, $slugs);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedRedirection(?string $host = null): array
    {
        $host ??= 'api-test-'.uniqid().'.example.com';
        $slug = 'old-'.uniqid();
        $this->request('POST', '/api/redirection/'.$host, [
            'slug' => $slug,
            'redirectTo' => 'https://target.example.com/',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $body = $this->decode();
        self::assertIsString($body['slug']);
        $this->createdPageIds[] = $this->lookupPageId($host, $body['slug']);

        return [$host, $body['slug']];
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $extraHeaders
     */
    private function request(string $method, string $url, array $body = [], array $extraHeaders = []): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken, 'CONTENT_TYPE' => 'application/json'] + $extraHeaders;
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

    private function lookupPageId(string $host, string $slug): int
    {
        $page = $this->em->getRepository(Page::class)->findOneBy(['host' => $host, 'slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);

        return $page->id ?? 0;
    }
}
