<?php

namespace Pushword\Conversation\Tests\Controller\Api;

use DateTime;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class ReviewApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<int> */
    private array $createdIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'review-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $em->persist($user);
        $em->flush();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($this->createdIds as $id) {
            // The Message repository so the plain messages seeded next to
            // reviews are swept too.
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
        $this->client->request(Request::METHOD_GET, '/api/review');
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

    public function testCreateWithTranslations(): void
    {
        $id = $this->seed([
            'locale' => 'en',
            'translations' => ['fr' => ['title' => 'Adoré', 'content' => 'Super produit']],
        ]);

        $this->request('GET', '/api/review/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSame(['fr' => ['title' => 'Adoré', 'content' => 'Super produit']], $this->decode()['translations']);
    }

    public function testPatchTranslationsOnlyTouchesTheLocalesSent(): void
    {
        $id = $this->seed(['translations' => [
            'fr' => ['title' => 'Titre', 'content' => 'Contenu'],
            'de' => ['title' => 'Titel', 'content' => 'Inhalt'],
        ]]);

        $this->request('PATCH', '/api/review/'.$id, [
            'translations' => ['fr' => ['title' => 'Nouveau titre', 'content' => 'Nouveau contenu']],
        ]);
        self::assertResponseIsSuccessful();

        $translations = $this->decode()['translations'];
        self::assertIsArray($translations);
        self::assertSame(['title' => 'Nouveau titre', 'content' => 'Nouveau contenu'], $translations['fr']);
        self::assertSame(['title' => 'Titel', 'content' => 'Inhalt'], $translations['de']);
    }

    public function testPatchNullTranslationRemovesTheLocale(): void
    {
        $id = $this->seed(['translations' => [
            'fr' => ['title' => 'Titre', 'content' => 'Contenu'],
            'de' => ['title' => 'Titel', 'content' => 'Inhalt'],
        ]]);

        $this->request('PATCH', '/api/review/'.$id, ['translations' => ['fr' => null]]);
        self::assertResponseIsSuccessful();

        $translations = $this->decode()['translations'];
        self::assertIsArray($translations);
        self::assertArrayNotHasKey('fr', $translations);
        self::assertArrayHasKey('de', $translations);
    }

    public function testTranslationsSurvivePatchingAnotherField(): void
    {
        $id = $this->seed(['translations' => ['fr' => ['title' => 'Titre', 'content' => 'Contenu']]]);

        $this->request('PATCH', '/api/review/'.$id, ['rating' => 2]);
        self::assertResponseIsSuccessful();

        $body = $this->decode();
        self::assertSame(2, $body['rating']);
        self::assertSame(['fr' => ['title' => 'Titre', 'content' => 'Contenu']], $body['translations']);
    }

    public function testPatchPartialTranslationReplacesTheLocalePair(): void
    {
        $id = $this->seed(['translations' => ['fr' => ['title' => 'Titre', 'content' => 'Contenu']]]);

        // An entry is the locale's whole pair: the half left out is dropped,
        // not kept — same contract as Review::setTranslation().
        $this->request('PATCH', '/api/review/'.$id, ['translations' => ['fr' => ['title' => 'Titre seul']]]);
        self::assertResponseIsSuccessful();

        self::assertSame(['fr' => ['title' => 'Titre seul']], $this->decode()['translations']);
    }

    public function testEmptyTranslationsMapChangesNothing(): void
    {
        $id = $this->seed(['translations' => ['fr' => ['title' => 'Titre', 'content' => 'Contenu']]]);

        // The map merges, so `{}` says "no locale to write", never "clear them".
        $this->request('PATCH', '/api/review/'.$id, ['translations' => []]);
        self::assertResponseIsSuccessful();

        self::assertSame(['fr' => ['title' => 'Titre', 'content' => 'Contenu']], $this->decode()['translations']);
    }

    public function testMalformedTranslationEntriesAreIgnored(): void
    {
        $id = $this->seed(['translations' => ['fr' => ['title' => 'Titre', 'content' => 'Contenu']]]);

        // A scalar where the pair belongs, and a key JSON gave as a number:
        // both skipped, and neither takes the stored translation down with it.
        $this->request('PATCH', '/api/review/'.$id, ['translations' => ['fr' => 'Bonjour', '42' => ['title' => 'X']]]);
        self::assertResponseIsSuccessful();

        self::assertSame(['fr' => ['title' => 'Titre', 'content' => 'Contenu']], $this->decode()['translations']);
    }

    public function testDelete(): void
    {
        $id = $this->seed();
        $response = $this->request('DELETE', '/api/review/'.$id);
        self::assertSame(204, $response->getStatusCode());

        $response = $this->request('GET', '/api/review/'.$id);
        self::assertSame(404, $response->getStatusCode());

        // Gone for the API, but the row must survive as a tombstone: it carries
        // the deletion to databases synced through the flat CSV.
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $em->clear();

        $review = $em->getRepository(Review::class)->find($id);
        self::assertInstanceOf(Review::class, $review);
        self::assertNotNull($review->deletedAt);
    }

    public function testListFiltersByHost(): void
    {
        $host = 'review-host-'.uniqid().'.example.com';
        $this->seed(['host' => $host]);
        $this->request('GET', '/api/review?host='.$host);
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $this->decode()['total']);
    }

    public function testListOnlyReturnsReviewsFromTheSharedTable(): void
    {
        $host = 'review-window-'.uniqid().'.example.com';
        $olderId = $this->persistReview($host, new DateTime('-2 minutes'));
        $messageId = $this->persistMessage($host, new DateTime('-1 minute'));
        $newerId = $this->persistReview($host, new DateTime());

        $this->request('GET', '/api/review?host='.$host);
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertSame(2, $body['total']);
        $ids = $this->listedIds($body);
        self::assertSame([$newerId, $olderId], $ids);
        self::assertNotContains($messageId, $ids);
    }

    public function testPaginationWindowLandingOnAMessageRow(): void
    {
        // Sorted by createdAt DESC the plain message sits between the two
        // reviews: with the table unrestricted, page 1 looked fine and this
        // window crashed hydrating a Message where a Review was expected.
        $host = 'review-window-'.uniqid().'.example.com';
        $olderId = $this->persistReview($host, new DateTime('-2 minutes'));
        $this->persistMessage($host, new DateTime('-1 minute'));
        $this->persistReview($host, new DateTime());

        $this->request('GET', '/api/review?host='.$host.'&per_page=1&page=2');
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertSame(2, $body['total']);
        self::assertSame([$olderId], $this->listedIds($body));
    }

    public function testAPlainMessageIdIs404OnTheReviewEndpoint(): void
    {
        $messageId = $this->persistMessage('example.com', new DateTime());
        $response = $this->request('GET', '/api/review/'.$messageId);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testEmptyContentFailsValidation(): void
    {
        $response = $this->request('POST', '/api/review', ['content' => '', 'host' => 'example.com']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    private function persistReview(string $host, DateTime $createdAt): int
    {
        $review = new Review();
        $review->host = $host;
        $review->setContent('Windowed review '.uniqid());
        $review->createdAt = $createdAt;

        return $this->persistEntity($review);
    }

    private function persistMessage(string $host, DateTime $createdAt): int
    {
        $message = new Message();
        $message->host = $host;
        $message->setContent('Windowed message '.uniqid());
        $message->createdAt = $createdAt;

        return $this->persistEntity($message);
    }

    private function persistEntity(Message $message): int
    {
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $em->persist($message);
        $em->flush();
        self::assertIsInt($message->id);
        $this->createdIds[] = $message->id;

        return $message->id;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<mixed>
     */
    private function listedIds(array $body): array
    {
        self::assertIsArray($body['items']);
        $ids = [];
        foreach ($body['items'] as $item) {
            self::assertIsArray($item);
            $ids[] = $item['id'];
        }

        return $ids;
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
