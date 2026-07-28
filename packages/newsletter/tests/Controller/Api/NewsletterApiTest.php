<?php

namespace Pushword\Newsletter\Tests\Controller\Api;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class NewsletterApiTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    private string $token = '';

    private string $userEmail = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = bin2hex(random_bytes(32));
        $this->userEmail = 'newsletter-api-'.uniqid().'@example.tld';
        // The app may replace the user entity; ask the repository which class it manages.
        $userClass = $this->userRepository()->getClassName();
        $user = new $userClass();
        $user->email = $this->userEmail;
        $user->setPassword('hashed');
        $user->apiToken = $this->token;
        $user->setRoles(['ROLE_EDITOR']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $user = $this->userRepository()->findOneBy(['email' => $this->userEmail]);
        if ($user instanceof User) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testAnonymousAccessIsRefused(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/newsletter/contact');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testCreatingAContactHonoursTheAudienceDoubleOptIn(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->getSlug(),
            'email' => 'api@example.tld',
            'name' => 'Robin',
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertSame('pending', $body['status']);
        self::assertSame('api', $body['source']);
        self::assertEmailCount(1);
    }

    /** Importing a base that already consented must not replay the confirmation ask. */
    public function testCreatingAContactAsSubscribedSkipsTheConfirmation(): void
    {
        $audience = $this->createAudience();

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->getSlug(),
            'email' => 'imported@example.tld',
            'status' => 'subscribed',
        ]);

        self::assertSame('subscribed', $body['status']);
        self::assertEmailCount(0);
    }

    public function testCreatingTwiceUpdatesRatherThanDuplicates(): void
    {
        $audience = $this->createAudience(requireDoubleOptIn: false);
        $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->getSlug(),
            'email' => 'twice@example.tld',
            'name' => 'First',
        ]);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->getSlug(),
            'email' => 'twice@example.tld',
            'name' => 'Second',
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame('Second', $body['name']);
        self::assertCount(1, $this->entityManager->getRepository(Contact::class)->findBy(['email' => 'twice@example.tld']));
    }

    public function testUnknownAudienceIsRefused(): void
    {
        $this->request(Request::METHOD_POST, '/api/newsletter/contact', ['audience' => 'nope', 'email' => 'a@b.tld']);

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidEmailIsRefused(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_POST, '/api/newsletter/contact', [
            'audience' => $audience->getSlug(),
            'email' => 'not-an-email',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /** A caller that knows one property must not have to preserve the others. */
    public function testPatchMergesCustomPropertiesAndRemovesOnNull(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'props@example.tld', customProperties: ['plan' => 'gold', 'city' => 'Grenoble']);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'customProperties' => ['lastBoughtProduct' => 'tmb', 'city' => null],
        ]);

        self::assertSame(['plan' => 'gold', 'lastBoughtProduct' => 'tmb'], $body['customProperties']);
    }

    public function testPatchReplacesTags(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'tags@example.tld', ['AmTrek']);

        $body = $this->request(Request::METHOD_PATCH, '/api/newsletter/contact/'.$contact->id, [
            'tags' => ['AmClient'],
        ]);

        self::assertSame(['AmClient'], $body['tags']);
    }

    public function testUnsubscribingThroughTheApi(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'gone@example.tld');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact/'.$contact->id.'/unsubscribe');

        self::assertSame('unsubscribed', $body['status']);
        self::assertSame(ContactStatus::Unsubscribed, $this->reload($contact)->getStatus());
    }

    public function testBouncingThroughTheApi(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'dead@example.tld');

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/contact/'.$contact->id.'/bounce');

        self::assertSame('bounced', $body['status']);
    }

    public function testListingBySegment(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);
        $this->createContact($audience, 'other@example.tld');

        $segment = json_encode([['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]);
        self::assertIsString($segment);

        $body = $this->request(
            Request::METHOD_GET,
            '/api/newsletter/contact?audience='.$audience->getSlug().'&segment='.urlencode($segment),
        );

        self::assertSame(1, $body['total']);
    }

    public function testAnInvalidSegmentIsAFourHundred(): void
    {
        $audience = $this->createAudience();
        $segment = json_encode([['field' => 'nope', 'op' => '=', 'value' => 'x']]);
        self::assertIsString($segment);

        $this->request(
            Request::METHOD_GET,
            '/api/newsletter/contact?audience='.$audience->getSlug().'&segment='.urlencode($segment),
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCampaignCreationReportsHowManyItWouldReach(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'a@example.tld', ['AmTrek']);
        $this->createContact($audience, 'b@example.tld');

        $created = $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->getSlug(),
            'subject' => 'Summer',
            'bodyMarkdown' => 'Hi **%name%**',
            'segment' => [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']],
        ]);

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $created['status']);

        $fetched = $this->request(Request::METHOD_GET, '/api/newsletter/campaign/'.$this->id($created));

        self::assertSame(1, $fetched['estimatedRecipients']);
    }

    public function testAnInvalidCampaignSegmentIsRejected(): void
    {
        $audience = $this->createAudience();

        $this->request(Request::METHOD_POST, '/api/newsletter/campaign', [
            'audience' => $audience->getSlug(),
            'subject' => 'Summer',
            'segment' => [['field' => 'tag', 'op' => 'olderThan', 'value' => '7d']],
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testSendingArmsTheCampaign(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/send');

        self::assertSame('sending', $body['status']);
        $stats = $body['stats'];
        self::assertIsArray($stats);
        self::assertSame(1, $stats['recipients']);
        self::assertEmailCount(0, null, 'the tick delivers, the API only arms');
    }

    public function testAnArmedCampaignCanNoLongerBeEdited(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/send');

        $this->request(Request::METHOD_PATCH, '/api/newsletter/campaign/'.$campaign->id, ['subject' => 'Changed']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testScheduling(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/schedule', [
            'scheduledAt' => '2030-01-01 09:00:00',
        ]);

        self::assertSame('scheduled', $body['status']);
        self::assertSame(CampaignStatus::Scheduled, $this->entityManager->getRepository(Campaign::class)->find($campaign->id)?->getStatus());
    }

    public function testSchedulingNeedsADate(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);

        $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/schedule', ['scheduledAt' => 'nonsense']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testTestSendTouchesNoCounter(): void
    {
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);

        $body = $this->request(Request::METHOD_POST, '/api/newsletter/campaign/'.$campaign->id.'/test', [
            'emails' => ['me@example.tld', 'broken'],
        ]);

        self::assertSame(['me@example.tld'], $body['sent']);
        self::assertSame(['broken'], $body['failed']);
        self::assertEmailCount(1);
        self::assertSame(0, $this->entityManager->getRepository(Campaign::class)->find($campaign->id)?->getSentCount());
    }

    private function userRepository(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    /** @param array<mixed> $body */
    private function id(array $body): int
    {
        $id = $body['id'] ?? null;
        self::assertIsInt($id);

        return $id;
    }

    private function reload(Contact $contact): Contact
    {
        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Contact::class)->find($contact->id);
        self::assertInstanceOf(Contact::class, $reloaded);

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function request(string $method, string $url, array $body = []): array
    {
        $this->client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'CONTENT_TYPE' => 'application/json',
        ], [] === $body ? '' : (string) json_encode($body));

        $content = (string) $this->client->getResponse()->getContent();
        $decoded = '' === $content ? [] : json_decode($content, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
