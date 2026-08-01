<?php

namespace Pushword\Newsletter\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `newsletter_csrf_protection` is on by default. It can be turned off, which is
 * what a static build served from another domain has to do: the token lives in
 * the session, and that cookie never comes back cross-site.
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

    /**
     * The token only exists inside a session the form endpoint opened, so the
     * only way to hold a valid one is the round trip a browser makes.
     */
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

        $this->post($audience->getSlug(), ['email' => 'no-token@example.tld']);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertInstanceOf(Contact::class, $this->entityManager->getRepository(Contact::class)
            ->findOneBy(['email' => 'no-token@example.tld']));
    }

    public function testAPostWithoutATokenIsRejectedByDefault(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'forged@example.tld']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager->getRepository(Contact::class)->findOneBy(['email' => 'forged@example.tld']));
    }

    public function testAPostCarryingTheWrongTokenIsRejected(): void
    {
        $audience = $this->createAudience();

        $this->post($audience->getSlug(), ['email' => 'wrong@example.tld', '_token' => 'not-the-one']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager->getRepository(Contact::class)->findOneBy(['email' => 'wrong@example.tld']));
    }

    public function testTheTokenTheFormEndpointIssuedIsAccepted(): void
    {
        $audience = $this->createAudience();

        $token = $this->tokenFromTheForm($audience->getSlug());

        $this->post($audience->getSlug(), ['email' => 'valid@example.tld', '_token' => $token]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertInstanceOf(Contact::class, $this->entityManager->getRepository(Contact::class)
            ->findOneBy(['email' => 'valid@example.tld']));
    }
}
