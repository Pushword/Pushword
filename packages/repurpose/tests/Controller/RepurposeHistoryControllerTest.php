<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Controller;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Entity\SocialPostRevision;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class RepurposeHistoryControllerTest extends WebTestCase
{
    private const string HOST = 'repurpose-history-test.example';

    private KernelBrowser $client;

    private string $email;

    private string $token;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->email = 'repurpose-history-'.uniqid().'@example.com';
        $this->token = bin2hex(random_bytes(32));

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->email;
        $user->apiToken = $this->token;
        $user->setPassword('hashed-password');
        $user->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $this->client->loginUser($user);
    }

    protected function tearDown(): void
    {
        $manager = $this->entityManager();
        foreach ($manager->getRepository(SocialPost::class)->findBy(['host' => self::HOST]) as $post) {
            $manager->remove($post);
        }

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = $manager->getRepository($userClass)->findOneBy(['email' => $this->email]);
        if (null !== $user) {
            $manager->remove($user);
        }

        $manager->flush();
        parent::tearDown();
    }

    public function testStudioAndApiWritesCanBeComparedAndRestored(): void
    {
        $post = $this->createPost('article');
        $studio = '/admin/repurpose/studio/'.$post->id;
        $history = $studio.'/history';

        $this->client->request(Request::METHOD_GET, $studio);
        self::assertSelectorExists('a[href="'.$history.'"][target="_blank"]');
        $this->client->clickLink('Version history');
        self::assertSelectorTextContains('.empty', 'Previous versions will appear here');

        $editedSlide = ['title' => 'Edited <script>headline</script>'];
        $studioSpec = [...$post->spec, 'slides' => [$editedSlide]];
        $second = $this->save($studio.'/save', $studioSpec, $post->revision());
        self::assertSame($second, $this->save($studio.'/save', $studioSpec, $second));

        $apiSpec = [
            ...$studioSpec,
            'status' => 'posted',
            'caption' => 'API caption',
            'slides' => [$editedSlide, ['title' => 'Café / chalet']],
        ];
        $this->client->jsonRequest(Request::METHOD_PUT, '/api/repurpose/'.self::HOST.'/linkedin/article', $apiSpec, [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'HTTP_IF_MATCH' => $second,
        ]);
        self::assertResponseIsSuccessful();

        $original = $this->client->request(Request::METHOD_GET, $history.'?version=1');
        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSelectorTextContains('[aria-current="page"] time', '2024');
        self::assertEqualsCanonicalizing(['slides[1].title', 'slides[2]', 'status', 'caption'], $original->filter('.field-name')->each(static fn (Crawler $field): string => $field->text()));
        self::assertSelectorTextContains('.comparison', 'Edited <script>headline</script>');
        self::assertSelectorNotExists('.values script');
        self::assertSelectorTextContains('.missing', 'Field absent');
        $diffValues = $original->filter('.values pre')->each(static fn (Crawler $value): string => $value->text(normalizeWhitespace: false));
        self::assertContains("{\n    \"title\": \"Café / chalet\"\n}", $diffValues);

        $this->client->submitForm('Compare', ['compare' => '2'], Request::METHOD_GET);
        self::assertSelectorTextContains('#compared-version', 'Version 2');
        self::assertSelectorTextContains('.values', 'Original headline');
        self::assertSelectorNotExists('#field-2');

        $this->client->submitForm('Restore version 1');
        self::assertResponseRedirects($studio, Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.studio-restored', 'Carousel version restored.');

        $persisted = $this->entityManager()->find(SocialPost::class, $post->id);
        self::assertInstanceOf(SocialPost::class, $persisted);
        self::assertSame($post->spec, $persisted->spec);
        self::assertSame('draft', $persisted->status);
        self::assertSame(4, $persisted->version);

        $versions = $this->entityManager()->getRepository(SocialPostRevision::class);
        self::assertSame([3, 2, 1], array_keys($versions->datesFor($persisted)));
        self::assertSame($apiSpec, $versions->findFor($persisted, 3)?->spec);

        $connection = $this->entityManager()->getConnection();
        $blob = $connection->fetchOne('SELECT spec FROM social_post_revision WHERE post_id = ? AND version = ?', [$post->id, 3]);
        $compressed = \is_resource($blob) ? stream_get_contents($blob) : $blob;
        self::assertIsString($compressed);
        self::assertSame(gzcompress(json_encode($apiSpec, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION), 1), $compressed);

        $currentJson = $connection->fetchOne('SELECT spec FROM social_post WHERE id = ?', [$post->id]);
        self::assertIsString($currentJson);
        self::assertSame($post->spec, json_decode($currentJson, true, flags: \JSON_THROW_ON_ERROR));

        $this->client->request(Request::METHOD_GET, $history.'?version=1');
        self::assertSelectorTextContains('.empty', 'These versions contain the same values.');
    }

    public function testRestoreRejectsStaleAndForgedRequests(): void
    {
        $post = $this->createPost('article');
        $studio = '/admin/repurpose/studio/'.$post->id;
        $history = $studio.'/history';
        $restore = $history.'/1/restore';
        $second = $this->save($studio.'/save', [...$post->spec, 'caption' => 'Second'], $post->revision());

        $page = $this->client->request(Request::METHOD_GET, $history);
        $staleForm = $page->selectButton('Restore version 1')->form();
        $latest = [...$post->spec, 'caption' => 'Third'];
        $third = $this->save($studio.'/save', $latest, $second);

        $this->client->request(Request::METHOD_POST, $restore, ['revision' => $third]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->client->submit($staleForm);
        self::assertResponseRedirects($history.'?version=1', Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'This carousel changed');
        $persisted = $this->entityManager()->find(SocialPost::class, $post->id);
        self::assertSame($latest, $persisted?->spec);

        $another = $this->createPost('unrelated');
        $another->updatedAt = new DateTime('2024-02-01 12:00:00');
        $this->entityManager()->flush();
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$another->id.'/history?version=1');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRestoreRejectsASourcePageAlreadyTakenByAnotherCarousel(): void
    {
        $post = $this->createPost('original');
        $studio = '/admin/repurpose/studio/'.$post->id;
        $this->save($studio.'/save', [...$post->spec, 'page' => 'moved'], $post->revision());
        $this->createPost('original');

        $this->client->request(Request::METHOD_GET, $studio.'/history');
        $this->client->submitForm('Restore version 1');
        self::assertResponseRedirects($studio.'/history?version=1', Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Another carousel already uses the saved source page');
        $persisted = $this->entityManager()->find(SocialPost::class, $post->id);
        self::assertInstanceOf(SocialPost::class, $persisted);
        self::assertSame('moved', $persisted->page);
        self::assertSame([1], array_keys($this->entityManager()->getRepository(SocialPostRevision::class)->datesFor($persisted)));
    }

    public function testRestoreRejectsAWriteAfterTheRevisionCheck(): void
    {
        $post = $this->createPost('article');
        $studio = '/admin/repurpose/studio/'.$post->id;
        $latest = [...$post->spec, 'caption' => 'Second'];
        $this->save($studio.'/save', $latest, $post->revision());
        $this->client->request(Request::METHOD_GET, $studio.'/history');

        $this->client->disableReboot();

        $events = $this->entityManager()->getEventManager();
        $concurrentWrite = new readonly class($post->id) {
            public function __construct(private ?int $postId)
            {
            }

            public function onFlush(OnFlushEventArgs $event): void
            {
                // Invalidate the loaded post just before the ORM's optimistic write.
                $event->getObjectManager()->getConnection()->executeStatement('UPDATE social_post SET version = version + 1 WHERE id = ?', [$this->postId]);
            }
        };
        $events->addEventListener([Events::onFlush], $concurrentWrite);

        try {
            $this->client->submitForm('Restore version 1');
        } finally {
            $events->removeEventListener([Events::onFlush], $concurrentWrite);
            $this->client->enableReboot();
        }

        self::assertResponseRedirects($studio.'/history?version=1', Response::HTTP_SEE_OTHER);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'This carousel changed');
        $persisted = $this->entityManager()->find(SocialPost::class, $post->id);
        self::assertInstanceOf(SocialPost::class, $persisted);
        self::assertSame($latest, $persisted->spec);
        self::assertSame([1], array_keys($this->entityManager()->getRepository(SocialPostRevision::class)->datesFor($persisted)));
    }

    public function testHistoryTracksOnlySpecEditsAndIsDeletedWithTheCarousel(): void
    {
        $post = $this->createPost('article');
        $manager = $this->entityManager();
        $revisions = $manager->getRepository(SocialPostRevision::class);
        $post->updatedAt = new DateTime('2024-02-01 12:00:00');
        $manager->flush();
        $previousDates = $revisions->datesFor($post);
        self::assertSame([], $previousDates);

        $version = $post->version;
        $reader = new EntityManager($manager->getConnection(), $manager->getConfiguration());
        $stale = $reader->find(SocialPost::class, $post->id);
        self::assertInstanceOf(SocialPost::class, $stale);
        $post->spec = [...$post->spec, 'caption' => 'New caption'];
        $manager->flush();
        $dates = $revisions->datesFor($post);
        self::assertSame([$version], array_keys($dates));
        self::assertSame('2024-02-01', $dates[$version]->format('Y-m-d'));
        self::assertSame([], $revisions->datesFor($stale));
        self::assertNull($revisions->findFor($stale, $version));

        self::assertTrue($manager->getRepository(SocialPost::class)->deleteAtVersion($post));
        self::assertNull($revisions->findFor($post, $version));
    }

    private function createPost(string $page): SocialPost
    {
        $post = new SocialPost();
        $post->host = self::HOST;
        $post->updatedAt = new DateTime('2024-01-15 12:00:00');
        $post->spec = [
            'page' => $page, 'network' => 'linkedin', 'format' => 'linkedin-4-5', 'status' => 'draft',
            'slides' => [['title' => 'Original headline']],
        ];

        $this->entityManager()->persist($post);
        $this->entityManager()->flush();

        return $post;
    }

    /** @param array<string, mixed> $spec */
    private function save(string $url, array $spec, string $revision): string
    {
        $this->client->jsonRequest(Request::METHOD_POST, $url, $spec, ['HTTP_IF_MATCH' => $revision]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsString($payload['revision']);

        return $payload['revision'];
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine.orm.default_entity_manager');
    }
}
