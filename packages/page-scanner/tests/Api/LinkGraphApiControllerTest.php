<?php

namespace Pushword\PageScanner\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class LinkGraphApiControllerTest extends WebTestCase
{
    private const string HOST = 'localhost.dev';

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
        $this->testUserEmail = 'link-graph-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();

        $this->removeSnapshots();
    }

    protected function tearDown(): void
    {
        $this->removeSnapshots();
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $this->em->remove($user);
            $this->em->flush();
        }

        parent::tearDown();
    }

    private function varDir(): string
    {
        /** @var string */
        return self::getContainer()->getParameter('pw.var_dir');
    }

    private function removeSnapshots(): void
    {
        new Filesystem()->remove([
            $this->varDir().'/page-scan-graph',
            $this->varDir().'/page-scan-graph--'.self::HOST,
        ]);
    }

    /**
     * @param list<string>                $nodes
     * @param array<string, list<string>> $edges
     */
    private function seed(array $nodes, array $edges): void
    {
        self::getContainer()->get(LinkGraphStorage::class)->write(self::HOST, $nodes, $edges);
    }

    public function testGraphRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/link-graph');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownHostIsRejected(): void
    {
        $this->request('/api/link-graph?host=does-not-exist.invalid');

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testNeverScannedPointsAtTheScanTrigger(): void
    {
        $body = $this->request('/api/link-graph?host='.self::HOST);

        // Read-only endpoint: it never starts a scan, it says where to.
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertSame('idle', $body['status']);
        self::assertSame('/api/page-scan?host='.self::HOST, $body['triggerUrl']);
    }

    public function testReturnsTheGraphOfTheLastScan(): void
    {
        $this->seed(
            [self::HOST.'/homepage', self::HOST.'/one'],
            [self::HOST.'/homepage' => [self::HOST.'/one']],
        );

        $body = $this->request('/api/link-graph?host='.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame('completed', $body['status']);
        self::assertSame(self::HOST, $body['host']);
        self::assertSame(2, $body['pageCount']);
        self::assertSame(1, $body['edgeCount']);
        self::assertSame(1, $body['orphanCount'], 'a single inbound link is not enough to stop being an orphan');
        self::assertNotNull($body['generatedAt']);

        self::assertIsArray($body['pages']);
        self::assertCount(2, $body['pages']);
    }

    public function testPageParamNarrowsToOneSlugWithItsSources(): void
    {
        $this->seed(
            [self::HOST.'/homepage', self::HOST.'/target', self::HOST.'/other'],
            [self::HOST.'/homepage' => [self::HOST.'/target'], self::HOST.'/other' => [self::HOST.'/target']],
        );

        $body = $this->request('/api/link-graph?host='.self::HOST.'&page=target');

        self::assertResponseIsSuccessful();
        self::assertIsArray($body['pages']);
        self::assertCount(1, $body['pages']);

        $page = $body['pages'][0];
        self::assertIsArray($page);
        self::assertSame('target', $page['slug']);
        self::assertSame(2, $page['inboundCount']);
        self::assertSame([self::HOST.'/homepage', self::HOST.'/other'], $page['inbound']);
        self::assertSame(1, $page['depth']);
    }

    public function testUnknownPageIsNotFound(): void
    {
        $this->seed([self::HOST.'/homepage'], []);

        $this->request('/api/link-graph?host='.self::HOST.'&page=does-not-exist');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testOrphanCountAndHostsWithoutHomepageAreReported(): void
    {
        $this->seed([self::HOST.'/lonely'], []);

        $body = $this->request('/api/link-graph?host='.self::HOST);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $body['orphanCount']);
        self::assertSame([self::HOST], $body['hostsWithoutHomepage']);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $url): array
    {
        $this->client->request(Request::METHOD_GET, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
