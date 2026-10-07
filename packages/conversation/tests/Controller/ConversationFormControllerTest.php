<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class ConversationFormControllerTest extends WebTestCase
{
    /** The `anonymous_content` rate limiter's `limit`, set in core's framework.php. */
    private const int SUBMISSION_LIMIT = 20;

    public function testNewsletterForm(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(Request::METHOD_GET, '/conversation/newsletter/test', [], [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString('pattern="[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{1,63}$"', (string) $client->getResponse()->getContent());
    }

    public function testMessageForm(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $crawler = $client->request(Request::METHOD_POST, '/conversation/message/test', [], [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => 'Ceci est un message de test',
        ]);
        $client->catchExceptions(false);
        $client->submit($form, [], $server);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidEmailProvider(): iterable
    {
        yield 'English' => ['en', 'Invalid email address'];
        yield 'French' => ['fr', 'Adresse email invalide'];
    }

    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailErrorIsTranslated(string $locale, string $expectedError): void
    {
        $client = self::createClient();
        $client->request(
            Request::METHOD_POST,
            '/conversation/message/test?locale='.$locale.'&host=localhost.dev',
            ['form' => ['authorEmail' => 'invalid-email', 'authorName' => 'Test', 'content' => 'Test message']],
        );

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($expectedError, $content);
        self::assertStringNotContainsString('conversationEmailInvalid', $content);
    }

    public function testContentPlaceholderIsRestoredOnBlur(): void
    {
        $client = self::createClient();
        self::getContainer()->get('translator')->getCatalogue('fr')->set('conversationContentPlaceholder', "L'avis");
        $crawler = $client->request(Request::METHOD_GET, '/conversation/message/test?locale=fr&host=localhost.dev');

        self::assertResponseIsSuccessful();
        self::assertSame("L'avis", $crawler->filter('#form_content')->attr('placeholder'));
        self::assertSame("this.placeholder = 'L\\u0027avis'", $crawler->filter('#form_content')->attr('onblur'));
    }

    public function testMessageFormDeduplication(): void
    {
        $client = self::createClient();
        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $uniqueContent = 'Dedup test message '.uniqid();

        // First submit
        $crawler = $client->request(Request::METHOD_POST, '/conversation/message/test', [], [], $server);
        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => $uniqueContent,
            'form[authorEmail]' => 'dedup@example.com',
            'form[authorName]' => 'Test',
        ]);
        $client->catchExceptions(false);
        $client->submit($form, [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Thank you', (string) $client->getResponse()->getContent());

        // Second submit with same content+email — should be deduplicated
        $crawler = $client->request(Request::METHOD_POST, '/conversation/message/test', [], [], $server);
        $form = $crawler->filter('[name="form"]')->form([
            'form[content]' => $uniqueContent,
            'form[authorEmail]' => 'dedup@example.com',
            'form[authorName]' => 'Test',
        ]);
        $client->submit($form, [], $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Thank you', (string) $client->getResponse()->getContent());

        // Verify only one message was persisted
        $em = self::getContainer()->get('doctrine')->getManager();
        $messages = $em->getRepository(Message::class)
            ->findBy(['content' => $uniqueContent]);
        self::assertCount(1, $messages, 'Duplicate message should not be persisted');
    }

    public function testMultiStepMessageUsesAnOpaqueOneTimeWorkflow(): void
    {
        $client = self::createClient();
        $server = [
            'HTTP_ORIGIN' => 'https://localhost.dev',
            'REMOTE_ADDR' => '192.0.2.51',
        ];
        $email = 'workflow-'.uniqid().'@example.tld';

        $crawler = $client->request(Request::METHOD_GET, '/conversation/newsletter/test?host=localhost.dev', server: $server);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $firstForm = $crawler->filter('[name="form"]')->form([
            'form[authorEmail]' => $email,
        ]);
        $firstAction = $firstForm->getUri();
        self::assertStringContainsString('token=', $firstAction);
        self::assertStringNotContainsString('id=', $firstAction);

        $crawler = $client->submit($firstForm, serverParameters: $server);
        self::assertResponseIsSuccessful();
        $secondForm = $crawler->filter('[name="form"]')->form([
            'form[authorName]' => 'Workflow Owner',
        ]);
        $secondAction = $secondForm->getUri();
        self::assertStringContainsString('step=2', $secondAction);

        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=localhost.dev&step=2&token='.str_repeat('a', 64),
            server: $server,
        );
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->submit($secondForm, serverParameters: $server);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Thank you', (string) $client->getResponse()->getContent());

        $message = self::getContainer()->get('doctrine')->getRepository(Message::class)
            ->findOneBy(['authorEmail' => $email]);
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('Workflow Owner', $message->authorName);

        $client->request(Request::METHOD_GET, $secondAction, server: $server);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testNewsletterFormWithQueryParams(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?locale=fr&host=localhost.dev',
            [],
            [],
            $server,
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testFormIsRenderedInTheRequestedLocale(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(
            Request::METHOD_GET,
            '/conversation/message/test?locale=fr&host=localhost.dev',
            [],
            [],
            $server,
        );
        $content = (string) $client->getResponse()->getContent();
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $content);
        self::assertStringContainsString('Valider', $content);
        self::assertStringNotContainsString('Submit', $content);

        // The next step must keep the requested locale, not fall back to the site's.
        self::assertStringContainsString('locale=fr', $content);

        // The listener resolves the locale through the `_locale` attribute, which lands
        // in the router context: generated URLs must not carry it as a query parameter.
        self::assertStringNotContainsString('_locale', $content);
    }

    public function testFormWithoutOriginHeader(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/conversation/newsletter/test');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertNull($client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testOriginFromTheBundleConfigIsAllowed(): void
    {
        $client = self::createClient();

        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=localhost.dev',
            [],
            [],
            ['HTTP_ORIGIN' => 'https://static.localhost.dev'],
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertSame('https://static.localhost.dev', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    /** Without `?host=`, the origin is checked against the site the request was sent to. */
    public function testOriginIsCheckedAgainstTheRequestHost(): void
    {
        $client = self::createClient();

        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test',
            server: ['HTTP_HOST' => 'pushword.piedweb.com', 'HTTP_ORIGIN' => 'https://pushword.piedweb.com'],
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://pushword.piedweb.com');
    }

    /** The origin check and the CORS headers belong to the conversation route only. */
    public function testOtherRoutesIgnoreTheOrigin(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/login', server: ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }

    /** @return iterable<string, array{string}> */
    public static function unauthorizedOriginProvider(): iterable
    {
        yield 'unrelated origin' => ['https://evil.example'];
        // Origins match exactly: starting like a trusted one is not enough.
        yield 'trusted origin as a prefix' => ['https://static.localhost.dev.evil.tld'];
        // What a browser sends from a sandboxed iframe or a file:// page.
        yield 'opaque origin' => ['null'];
        // Each site trusts its own hosts, not the other sites of the install.
        yield 'another site of the install' => ['https://pushword.piedweb.com'];
        // Outside the dev environment, a site host is trusted over https only.
        yield 'site host over plain http' => ['http://localhost.dev'];
    }

    #[DataProvider('unauthorizedOriginProvider')]
    public function testUnauthorizedOriginIsForbidden(string $origin): void
    {
        $client = self::createClient();

        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=localhost.dev',
            server: ['HTTP_ORIGIN' => $origin],
        );

        $content = (string) $client->getResponse()->getContent();
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), $content);
        // The exact message: the refusal must not reveal which origins are trusted.
        self::assertSelectorTextSame('title', 'Origin `'.$origin.'` is not allowed to load conversation forms. (403 Forbidden)');
        self::assertNull($client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * CORS only hides the response from the browser: a cross-origin form POST still
     * reaches the server, so the origin check is what keeps it from being saved.
     */
    public function testUnauthorizedOriginCannotPostAMessage(): void
    {
        $client = self::createClient();
        $content = 'Cross-origin message '.uniqid();
        $postFrom = static fn (string $origin): mixed => $client->request(
            Request::METHOD_POST,
            '/conversation/message/test?host=localhost.dev',
            ['form' => ['authorEmail' => 'cross-origin@example.tld', 'authorName' => 'Test', 'content' => $content]],
            server: ['HTTP_ORIGIN' => $origin, 'REMOTE_ADDR' => '192.0.2.52'],
        );
        $savedMessages = static fn (): array => self::getContainer()->get('doctrine')->getRepository(Message::class)
            ->findBy(['content' => $content]);

        $postFrom('https://evil.example');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertCount(0, $savedMessages());

        // The same submission from the site's own origin is saved: the origin alone was refused.
        $postFrom('https://localhost.dev');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $savedMessages());
    }

    public function testUnauthorizedOriginDoesNotSpendTheVisitorRateLimit(): void
    {
        $client = self::createClient();
        $postFrom = static fn (string $origin): mixed => $client->request(
            Request::METHOD_POST,
            '/conversation/message/test?host=localhost.dev',
            server: ['HTTP_ORIGIN' => $origin, 'REMOTE_ADDR' => '192.0.2.53'],
        );

        // A hostile page makes its visitor's browser post as often as the limit allows.
        for ($attempt = 1; $attempt <= self::SUBMISSION_LIMIT; ++$attempt) {
            $postFrom('https://evil.example');
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }

        $postFrom('https://localhost.dev');
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{?string, string}> */
    public static function rateLimitedOriginProvider(): iterable
    {
        // A statically generated page posting to its live host.
        yield 'trusted cross-origin page' => ['https://static.localhost.dev', '192.0.2.54'];
        // A script posting directly sends no Origin: it is the case the limiter exists for.
        yield 'no origin' => [null, '192.0.2.55'];
    }

    #[DataProvider('rateLimitedOriginProvider')]
    public function testSubmissionsBeyondTheLimitAreRefused(?string $origin, string $clientIp): void
    {
        $client = self::createClient();
        $server = ['REMOTE_ADDR' => $clientIp];
        if (null !== $origin) {
            $server['HTTP_ORIGIN'] = $origin;
        }

        $url = '/conversation/message/test?host=localhost.dev';

        for ($attempt = 1; $attempt <= self::SUBMISSION_LIMIT; ++$attempt) {
            $client->request(Request::METHOD_POST, $url, server: $server);
            self::assertResponseIsSuccessful();
        }

        $client->request(Request::METHOD_POST, $url, server: $server);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertResponseHasHeader('Retry-After');
        // A trusted page posting cross-origin must be able to read the refusal and its delay.
        $corsHeaders = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers' => 'Retry-After',
        ];
        foreach ($corsHeaders as $name => $value) {
            self::assertSame(null === $origin ? null : $value, $client->getResponse()->headers->get($name), $name);
        }

        // Only submissions are limited: the form can still be displayed.
        $client->request(Request::METHOD_GET, $url, server: $server);
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{string}> */
    public static function notFoundProvider(): iterable
    {
        yield 'unknown form type' => ['/conversation/unknown-type/test?host=localhost.dev'];
        yield 'step out of range' => ['/conversation/newsletter/test?host=localhost.dev&step=99'];
        // Steps are numbered from 1.
        yield 'step zero' => ['/conversation/newsletter/test?host=localhost.dev&step=0'];
        yield 'step the form does not have' => ['/conversation/message/test?host=localhost.dev&step=2'];
        yield 'expired workflow' => ['/conversation/newsletter/test?host=localhost.dev&step=2&token='.str_repeat('a', 64)];
    }

    /**
     * A URL the form cannot answer is not found, not a server error, and a trusted
     * cross-origin page can read that refusal instead of a bare network error.
     */
    #[DataProvider('notFoundProvider')]
    public function testUnknownConversationIsNotFoundAndReadableCrossOrigin(string $url): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, $url, server: ['HTTP_ORIGIN' => 'https://static.localhost.dev']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://static.localhost.dev');
    }

    public function testConversationWithSlashInReferring(): void
    {
        $client = self::createClient();

        $server = ['HTTP_ORIGIN' => 'https://localhost.dev'];
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/some/path/with/slashes?locale=en&host=localhost.dev',
            [],
            [],
            $server,
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    /**
     * Regression test for a worker-mode state leak: this controller is a shared
     * service, so under a long-running worker (FrankenPHP, RoadRunner…) the same
     * instance handles successive requests. It used to cache $form / $possibleOrigins
     * across requests, pinning every later request to the first one's site — so a
     * second request from a different host saw the first host's allowed origins and
     * was rejected with "origin sent is not authorized". disableReboot() reuses the
     * kernel between requests to reproduce that worker reuse.
     */
    public function testStateDoesNotLeakAcrossRequestsWhenKernelIsReused(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        // Request #1 resolves the localhost.dev site and its allowed origins.
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=localhost.dev',
            [],
            [],
            ['HTTP_ORIGIN' => 'https://localhost.dev'],
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertSame('https://localhost.dev', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        // Request #2 targets a different site whose origin is only valid once the
        // per-request state is rebuilt. Without the reset it 500s on the cached origins.
        $client->request(
            Request::METHOD_GET,
            '/conversation/newsletter/test?host=pushword.piedweb.com',
            [],
            [],
            ['HTTP_ORIGIN' => 'https://pushword.piedweb.com'],
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertSame('https://pushword.piedweb.com', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }
}
