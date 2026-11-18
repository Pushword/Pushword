<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Controller;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Controller\ConversationFormController;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\Extension\SpamProtectionExtension;
use Pushword\Conversation\Service\ClientNetwork;
use Pushword\Conversation\Tests\Form\Fixtures\HomepageFieldMessageForm;
use Pushword\Conversation\Tests\Form\Fixtures\SiteBuiltDelayedMessageForm;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\RateLimiter\LimiterInterface;

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
        yield 'honeypot holding a falsy "0"' => ['message', 'honeypot-zero'];
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
        } elseif ('honeypot-zero' === $case) {
            $values['form'][SpamProtectionExtension::HONEYPOT] = '0';
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
        $this->quota()->consume(self::QUOTA);

        foreach (['localhost.dev', 'pushword.piedweb.com'] as $host) {
            $email = 'over-quota-'.uniqid().'@example.org';
            $crawler = $this->submit('message', $this->messageFields('message', $email, 'One message too many.'), $host);

            self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);
            self::assertSame($email, $crawler->filter('input[name="form[authorEmail]"]')->attr('value'));
            self::assertEmailCount(0);
            self::assertNull($this->findMessage($email), $host);
        }

        // A step that gives no address still passes: the refusal waits for the one that does.
        $content = 'A two step message over quota '.uniqid();
        $crawler = $this->submit('ms-message', ['form[content]' => $content]);
        self::assertSelectorNotExists('[role="alert"]');
        $this->submitForm($crawler, ['form[authorName]' => 'Camille', 'form[authorEmail]' => 'over-quota-'.uniqid().'@example.org']);
        self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);
        self::assertEmailCount(0);
        // The refused step leaves the saved first step without an identity.
        $firstStep = $this->findMessageBy(['content' => $content]);
        self::assertNotNull($firstStep);
        self::assertSame('', $firstStep->authorEmail);
        self::assertSame('', $firstStep->authorName);

        $this->client->setServerParameter('REMOTE_ADDR', $this->newIp());
        $email = 'other-ip-'.uniqid().'@example.org';
        $this->submit('message', $this->messageFields('message', $email, 'A message from another connection.'));
        self::assertEmailCount(1);
        self::assertNotNull($this->findMessage($email));
    }

    public function testOneIpv6NetworkSharesTheQuota(): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', '2001:db8:57:1::1');
        for ($sent = 1; $sent <= self::QUOTA; ++$sent) {
            $this->submit('message', $this->messageFields('message', 'network-'.uniqid().'@example.org', 'Message '.uniqid()));
        }

        // An IPv6 visitor holds a whole /64: another address of it is the same visitor.
        $this->client->setServerParameter('REMOTE_ADDR', '2001:db8:57:1:ffff:ffff:ffff:ffff');
        $email = 'same-network-'.uniqid().'@example.org';
        $this->submit('message', $this->messageFields('message', $email, 'One more message.'));
        self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);
        self::assertNull($this->findMessage($email));

        $this->client->setServerParameter('REMOTE_ADDR', '2001:db8:57:2::1');
        $email = 'next-network-'.uniqid().'@example.org';
        $this->submit('message', $this->messageFields('message', $email, 'One more message.'));
        self::assertNotNull($this->findMessage($email));
    }

    public function testARefusedMessageGoesThroughUnchangedOnceTheQuotaRecovers(): void
    {
        $this->quota()->consume(self::QUOTA);
        $email = 'retry-'.uniqid().'@example.org';
        $crawler = $this->submit('message', $this->messageFields('message', $email, 'A message sent again later.'));
        self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);

        $this->quota()->reset();
        $this->submitForm($crawler, []);

        // Saved and notified, not mistaken for a duplicate of the refused attempt.
        self::assertSelectorNotExists('[name="form"]');
        self::assertEmailCount(1);
        self::assertNotNull($this->findMessage($email));
    }

    public function testRefusalsAnswerInTheFormLocale(): void
    {
        $form = $this->client->request(Request::METHOD_GET, $this->url('message', locale: 'fr'))
            ->filter('[name="form"]')->form($this->messageFields('message', 'fr-'.uniqid().'@example.org', 'Une question sur le séjour.'));

        $this->client->request(Request::METHOD_POST, $this->unsigned($form->getUri()), $form->getPhpValues());
        self::assertSelectorTextContains('[role="alert"]', "Votre message n'a pas pu être envoyé.");

        $this->quota()->consume(self::QUOTA);
        $this->client->submit($form);
        self::assertSelectorTextContains('[role="alert"]', 'Trop de messages ont été envoyés depuis votre connexion.');
    }

    public function testNewsletterShowsRefusalsBelowItsInlineRow(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, $this->url('newsletter'));
        $form = $crawler->filter('[name="form"]')->form(['form[authorEmail]' => 'not-an-email']);
        $this->client->submit($form);
        self::assertSelectorNotExists('[role="alert"]', 'a field error keeps the red border only');

        $form->setValues(['form[authorEmail]' => 'newsletter-'.uniqid().'@example.org']);
        $this->client->request(Request::METHOD_POST, $this->unsigned($form->getUri()), $form->getPhpValues());
        self::assertSelectorTextContains('[role="alert"]', self::REJECTED);

        $this->quota()->consume(self::QUOTA);
        $this->client->submit($form);
        self::assertSelectorTextContains('[role="alert"]', self::RATE_LIMITED);

        $this->quota()->reset();
        $nameStep = $this->client->submit($form)->filter('[name="form"]')->form(['form[authorName]' => 'Camille']);
        $this->client->request(Request::METHOD_POST, $this->unsigned($nameStep->getUri()), $nameStep->getPhpValues());
        self::assertSelectorTextContains('[role="alert"]', self::REJECTED);
    }

    public function testAFormRenderedServerSideAcceptsItsVisitorAndCarriesTheTraps(): void
    {
        $crawler = new Crawler($this->renderInPage(['type' => 'newsletter', 'referring' => 'footer']), 'https://localhost.dev/');
        $email = 'embedded-'.uniqid().'@example.org';
        $this->client->submit($crawler->filter('[name="form"]')->form(['form[authorEmail]' => $email]));

        self::assertSelectorNotExists('[role="alert"]');
        self::assertEmailCount(1);
        self::assertNotNull($this->findMessage($email));
        self::assertSame(self::QUOTA - 1, $this->remainingQuota());
        self::assertCount(1, $crawler->filter('[hidden] input[name="form['.SpamProtectionExtension::HONEYPOT.']"]'));
        self::assertTrue($this->signer()->check((string) $crawler->filter('[name="form"]')->attr('action')));
    }

    public function testOnlyTheConversationControllerGetsTheTraps(): void
    {
        $requestStack = self::getContainer()->get('request_stack');
        $controllers = [ConversationFormController::class.'::show' => true, 'App\\Controller\\OtherController::show' => false];

        foreach ($controllers as $controller => $guarded) {
            $requestStack->push(new Request(attributes: ['_controller' => $controller]));
            $form = self::getContainer()->get('form.factory')->create(FormType::class, new Message());
            $requestStack->pop();

            self::assertSame($guarded, $form->has(SpamProtectionExtension::HONEYPOT), $controller);
        }
    }

    public function testACrossOriginPageSubmitsWithoutAnyCookie(): void
    {
        $this->client->setServerParameter('HTTP_ORIGIN', 'https://static.localhost.dev');
        $crawler = $this->client->request(Request::METHOD_GET, $this->url('message'));
        self::assertSame([], $this->client->getResponse()->headers->getCookies());

        $this->client->getCookieJar()->clear();
        $email = 'cross-origin-'.uniqid().'@example.org';
        $this->submitForm($crawler, $this->messageFields('message', $email, 'A message from a static page.'));

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://static.localhost.dev');
        self::assertSelectorNotExists('[name="form"]');
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

    /**
     * Renders the form the way a site template embeds it, through an inline sub-request.
     *
     * @param array<string, string> $attributes
     */
    private function renderInPage(array $attributes): string
    {
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(Request::create('https://localhost.dev/'));

        try {
            return self::getContainer()->get('twig')
                ->createTemplate('{{ render(controller(controller, attributes)) }}')
                ->render(['controller' => ConversationFormController::class.'::show', 'attributes' => $attributes]);
        } finally {
            $requestStack->pop();
        }
    }

    private function url(string $type, string $host = 'localhost.dev', string $locale = 'en'): string
    {
        return '/conversation/'.$type.'/test?host='.$host.'&locale='.$locale;
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
        return $this->findMessageBy(['authorEmail' => $email]);
    }

    /**
     * @param array<string, string> $criteria
     *
     * @phpstan-impure
     */
    private function findMessageBy(array $criteria): ?Message
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->getRepository(Message::class)->findOneBy($criteria);
    }

    private function remainingQuota(): int
    {
        return $this->quota()->consume(0)->getRemainingTokens();
    }

    private function quota(): LimiterInterface
    {
        return self::getContainer()->get('limiter.conversation_submit')->create(ClientNetwork::of($this->ip));
    }

    private function signer(): UriSigner
    {
        return self::getContainer()->get(UriSigner::class);
    }

    private function newIp(): string
    {
        // A /64 of its own: the quota counts an IPv6 visitor by network.
        return \sprintf('2001:db8:%x:%x::1', random_int(1, 0xFFFF), random_int(1, 0xFFFF));
    }
}
