<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Controller;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\Extension\SpamProtectionExtension;
use Pushword\Conversation\Tests\Form\Fixtures\HomepageFieldMessageForm;
use Pushword\Conversation\Tests\Form\Fixtures\SiteBuiltDelayedMessageForm;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[Group('integration')]
final class ConversationSpamProtectionTest extends WebTestCase
{
    use MailerAssertionsTrait;

    /** The `conversation_submit` limit, set in the bundle's framework.php. */
    private const int QUOTA = 10;

    private const string REJECTED = 'Your message could not be sent.';

    private const string RATE_LIMITED = 'Too many messages were sent from your connection.';

    private const string SPAM = 'Salam, qiymətinizi bilmək istədim.';

    private KernelBrowser $client;

    private string $ip;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        // The limiter outlives a test in the worker's cache: each test spends its own allowance.
        $this->ip = $this->newIp();
        $this->client->setServerParameter('REMOTE_ADDR', $this->ip);

        $site = self::getContainer()->get(SiteRegistry::class)->get('localhost.dev');
        $site->setCustomProperty('conversation_notification_email_from', 'notifications@example.org');
        $site->setCustomProperty('conversation_notification_email_to', 'team@example.org');
        $site->setCustomProperty('conversation_form_site_built', SiteBuiltDelayedMessageForm::class);
        $site->setCustomProperty('conversation_form_homepage_field', HomepageFieldMessageForm::class);
    }

    /** @return iterable<string, array{string}> */
    public static function publicFormProvider(): iterable
    {
        yield 'message' => ['message'];
        yield 'multi-step message' => ['ms-message'];
        yield 'newsletter, with its own form theme' => ['newsletter'];
        yield 'site form built on FormType' => ['site-built'];
    }

    #[DataProvider('publicFormProvider')]
    public function testPublicFormsCarryAHiddenHoneypotAndASignedAction(string $type): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, $this->url($type));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[hidden] input[name="form['.SpamProtectionExtension::HONEYPOT.']"][tabindex="-1"][autocomplete="off"]');
        $action = (string) $crawler->filter('[name="form"]')->attr('action');
        self::assertStringContainsString('_expiration=', $action);
        self::assertTrue($this->signer()->check($action));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rejectedSubmissionProvider(): iterable
    {
        yield 'filled honeypot' => ['message', 'honeypot'];
        yield 'honeypot sent as a list' => ['message', 'honeypot-list'];
        yield 'filled honeypot in a site form built on FormType' => ['site-built', 'honeypot'];
        yield 'unsigned action' => ['message', 'unsigned'];
        yield 'action signed for another page' => ['message', 'other-page'];
        yield 'expired action' => ['message', 'expired'];
    }

    #[DataProvider('rejectedSubmissionProvider')]
    public function testRejectedSubmissionIsNotSavedNotifiedNorCounted(string $type, string $case): void
    {
        $email = 'spam-'.uniqid().'@example.org';
        $form = $this->client->request(Request::METHOD_GET, $this->url($type))
            ->filter('[name="form"]')->form($this->messageFields($type, $email, self::SPAM));
        $values = $form->getPhpValues();
        self::assertIsArray($values['form']);
        $uri = $form->getUri();

        if ('honeypot' === $case) {
            $values['form'][SpamProtectionExtension::HONEYPOT] = 'https://spam.example';
        } elseif ('honeypot-list' === $case) {
            $values['form'][SpamProtectionExtension::HONEYPOT] = ['https://spam.example'];
        } elseif ('unsigned' === $case) {
            $uri = $this->unsigned($uri);
        } elseif ('other-page' === $case) {
            $uri = str_replace('/conversation/message/test?', '/conversation/message/other?', $uri);
        } elseif ('expired' === $case) {
            $uri = $this->signer()->sign($this->unsigned($uri), new DateTimeImmutable('-1 minute'));
        }

        $crawler = $this->client->request(Request::METHOD_POST, $uri, $values);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', self::REJECTED);
        self::assertSame(self::SPAM, $crawler->filter('textarea[name="form[content]"]')->text());
        self::assertEmailCount(0);
        self::assertNull($this->findMessage($email));
        self::assertSame(self::QUOTA, $this->remainingQuota());
    }

    public function testAVisitorResendsAnExpiredFormFromTheRedisplayedOne(): void
    {
        $email = 'visitor-'.uniqid().'@example.org';
        $form = $this->client->request(Request::METHOD_GET, $this->url('message'))
            ->filter('[name="form"]')->form($this->messageFields('message', $email, 'A question about the trip.'));

        $expired = $this->signer()->sign($this->unsigned($form->getUri()), new DateTimeImmutable('-1 minute'));
        $crawler = $this->client->request(Request::METHOD_POST, $expired, $form->getPhpValues());
        self::assertSelectorTextContains('[role="alert"]', self::REJECTED);

        $this->client->submit($crawler->filter('[name="form"]')->form());

        self::assertSelectorNotExists('[name="form"]');
        self::assertEmailCount(1);
        self::assertNotNull($this->findMessage($email));
        self::assertSame(self::QUOTA - 1, $this->remainingQuota());
    }

    public function testQuotaCountsEachMessageOnceWhenItFirstGetsAnAddressToNotify(): void
    {
        // liveBlock() loads a form with an empty POST.
        $this->client->request(Request::METHOD_GET, $this->url('message'));
        $this->client->request(Request::METHOD_POST, $this->url('message'));
        self::assertSelectorExists('[name="form"]');
        self::assertSame(self::QUOTA, $this->remainingQuota(), 'loading a form');

        $this->submit('message', $this->messageFields('message', 'not-an-email', 'An invalid message.'));
        self::assertSelectorExists('[name="form"]');
        self::assertEmailCount(0);
        self::assertSame(self::QUOTA, $this->remainingQuota(), 'invalid form');

        $this->submit('message', $this->messageFields('message', 'single-'.uniqid().'@example.org', 'A single step message.'));
        self::assertEmailCount(1);
        self::assertSame(self::QUOTA - 1, $this->remainingQuota(), 'single step message');

        $crawler = $this->submit('ms-message', ['form[content]' => 'A two step message.']);
        self::assertSelectorExists('input[name="form[authorEmail]"]');
        self::assertEmailCount(0);
        self::assertSame(self::QUOTA - 1, $this->remainingQuota(), 'content step without an address');

        $this->submitForm($crawler, ['form[authorName]' => 'Camille', 'form[authorEmail]' => 'two-steps-'.uniqid().'@example.org']);
        self::assertEmailCount(1);
        self::assertSame(self::QUOTA - 2, $this->remainingQuota(), 'identity step');

        $crawler = $this->submit('newsletter', ['form[authorEmail]' => 'newsletter-'.uniqid().'@example.org']);
        self::assertEmailCount(1);
        self::assertSame(self::QUOTA - 3, $this->remainingQuota(), 'newsletter email step');

        $this->submitForm($crawler, ['form[authorName]' => 'Camille']);
        self::assertSelectorNotExists('[name="form"]');
        self::assertEmailCount(0);
        self::assertSame(self::QUOTA - 3, $this->remainingQuota(), 'newsletter name step');

        $email = 'delayed-'.uniqid().'@example.org';
        $this->submit('site-built', $this->messageFields('site-built', $email, 'A message notified later.'));
        self::assertEmailCount(0);
        self::assertNotNull($this->findMessage($email)?->notificationSendAfter);
        self::assertSame(self::QUOTA - 4, $this->remainingQuota(), 'delayed notification');
    }

    public function testSpentQuotaRefusesNewMessagesOnEveryHostFromThatIpOnly(): void
    {
        $this->limiter()->create($this->ip)->consume(self::QUOTA);

        foreach (['localhost.dev', 'pushword.piedweb.com'] as $host) {
            $email = 'over-quota-'.uniqid().'@example.org';
            $crawler = $this->submit('message', $this->messageFields('message', $email, 'One message too many.'), $host);

            self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);
            self::assertSame($email, $crawler->filter('input[name="form[authorEmail]"]')->attr('value'));
            self::assertEmailCount(0);
            self::assertNull($this->findMessage($email), $host);
        }

        // A step that gives no address still passes: the refusal waits for the one that does.
        $crawler = $this->submit('ms-message', ['form[content]' => 'A two step message over quota.']);
        self::assertSelectorNotExists('[role="alert"]');
        $this->submitForm($crawler, ['form[authorName]' => 'Camille', 'form[authorEmail]' => 'over-quota-'.uniqid().'@example.org']);
        self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);
        self::assertEmailCount(0);

        $this->client->setServerParameter('REMOTE_ADDR', $this->newIp());
        $email = 'other-ip-'.uniqid().'@example.org';
        $this->submit('message', $this->messageFields('message', $email, 'A message from another connection.'));
        self::assertEmailCount(1);
        self::assertNotNull($this->findMessage($email));
    }

    public function testASiteFieldNamedLikeTheHoneypotReplacesItInsteadOfRejectingVisitors(): void
    {
        $this->client->request(Request::METHOD_GET, $this->url('homepage-field'));
        self::assertSelectorExists('input[type="url"][name="form['.SpamProtectionExtension::HONEYPOT.']"]');
        self::assertSelectorNotExists('[hidden] input[name="form['.SpamProtectionExtension::HONEYPOT.']"]');

        $email = 'homepage-'.uniqid().'@example.org';
        $this->submit('homepage-field', [
            ...$this->messageFields('message', $email, 'A request with my website.'),
            'form['.SpamProtectionExtension::HONEYPOT.']' => 'https://www.example.org',
        ]);

        self::assertSelectorNotExists('[name="form"]');
        self::assertEmailCount(1);
        self::assertNotNull($this->findMessage($email));
    }

    private function url(string $type, string $host = 'localhost.dev'): string
    {
        return '/conversation/'.$type.'/test?host='.$host.'&locale=en';
    }

    /** @return array<string, string> */
    private function messageFields(string $type, string $email, string $content): array
    {
        $fields = ['form[authorEmail]' => $email, 'form[content]' => $content];

        return 'message' === $type ? ['form[authorName]' => 'Camille', ...$fields] : $fields;
    }

    /** @param array<string, string> $fields */
    private function submit(string $type, array $fields, string $host = 'localhost.dev'): Crawler
    {
        return $this->submitForm($this->client->request(Request::METHOD_GET, $this->url($type, $host)), $fields);
    }

    /** @param array<string, string> $fields */
    private function submitForm(Crawler $crawler, array $fields): Crawler
    {
        $crawler = $this->client->submit($crawler->filter('[name="form"]')->form($fields));
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function unsigned(string $uri): string
    {
        [$path, $query] = explode('?', $uri, 2);
        parse_str($query, $parameters);
        unset($parameters['_expiration'], $parameters['_hash']);

        return $path.'?'.http_build_query($parameters);
    }

    private function findMessage(string $email): ?Message
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->getRepository(Message::class)->findOneBy(['authorEmail' => $email]);
    }

    private function remainingQuota(): int
    {
        return $this->limiter()->create($this->ip)->consume(0)->getRemainingTokens();
    }

    private function limiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.conversation_submit');
    }

    private function signer(): UriSigner
    {
        return self::getContainer()->get(UriSigner::class);
    }

    private function newIp(): string
    {
        return \sprintf('2001:db8::%x:%x', random_int(1, 0xFFFF), random_int(1, 0xFFFF));
    }
}
