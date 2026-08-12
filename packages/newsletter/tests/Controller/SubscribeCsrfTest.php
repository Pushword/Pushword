<?php

namespace Pushword\Newsletter\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `newsletter_csrf_protection` is on by default, and stays on everywhere: the
 * token is signed rather than kept in a session, so a static build served from
 * another domain — which has no cookie to send back — passes it just the same.
 */
#[Group('integration')]
final class SubscribeCsrfTest extends AbstractNewsletterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::getContainer()->get('cache.app')->deleteItem('pushword_newsletter_subscribe_'.md5('127.0.0.1'));
    }

    protected function tearDown(): void
    {
        $this->enableCsrf();
        parent::tearDown();
    }

    private function enableCsrf(bool $enabled = true): void
    {
        self::getContainer()->get(SiteRegistry::class)->get()->setCustomProperty('newsletter_csrf_protection', $enabled);
    }

    /** The only way to hold a valid token is the round trip a browser makes. */
    private function tokenFromTheForm(string $audienceSlug): string
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/form?audiences='.$audienceSlug);

        $html = (string) $this->client->getResponse()->getContent();
        self::assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $html, $matches));

        return $matches[1];
    }

    /** @param array<string, string> $parameters */
    private function post(string $audienceSlug, array $parameters): void
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/subscribe', ['audience' => $audienceSlug] + $parameters);
    }

    public function testTurningTheSettingOffAcceptsATokenlessPost(): void
    {
        $audience = $this->createAudience();
        $this->enableCsrf(false);

        $this->post($audience->slug, ['email' => 'no-token@example.tld']);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertInstanceOf(Contact::class, $this->entityManager->getRepository(Contact::class)
            ->findOneBy(['email' => 'no-token@example.tld']));
    }

    public function testAPostWithoutATokenIsRejectedByDefault(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->slug, ['email' => 'forged@example.tld']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager->getRepository(Contact::class)->findOneBy(['email' => 'forged@example.tld']));
    }

    public function testAPostCarryingTheWrongTokenIsRejected(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->slug, ['email' => 'wrong@example.tld', '_token' => 'not-the-one']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager->getRepository(Contact::class)->findOneBy(['email' => 'wrong@example.tld']));
    }

    public function testTheTokenTheFormEndpointIssuedIsAccepted(): void
    {
        $audience = $this->createAudience();

        $token = $this->tokenFromTheForm($audience->slug);

        $this->post($audience->slug, ['email' => 'valid@example.tld', '_token' => $token]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertInstanceOf(Contact::class, $this->entityManager->getRepository(Contact::class)
            ->findOneBy(['email' => 'valid@example.tld']));
    }

    /**
     * The deployment the whole design is for: a page built once on one domain,
     * fetching its form from the live host on another. Dropping every cookie
     * between the two requests is what that browser does — Safari refuses the
     * third-party cookie outright, and the rest partition it away.
     */
    public function testTheTokenSurvivesABrowserThatKeepsNoCookie(): void
    {
        $audience = $this->createAudience();

        $token = $this->tokenFromTheForm($audience->slug);
        $this->client->getCookieJar()->clear();

        $this->post($audience->slug, ['email' => 'cookieless@example.tld', '_token' => $token]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertInstanceOf(Contact::class, $this->entityManager->getRepository(Contact::class)
            ->findOneBy(['email' => 'cookieless@example.tld']));
    }
}
