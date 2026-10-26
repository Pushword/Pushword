<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Click;

use DateTimeImmutable;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Click\ClickTracker;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Service\EmailMarkdownRenderer;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Pushword\Newsletter\Service\LinkGenerator;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Statistics\LocalStatisticsProvider;
use Pushword\Newsletter\Statistics\ReportQuery;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Utm\UtmDecorator;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class ScopedClickTrackingTest extends AbstractNewsletterTestCase
{
    public function testNewBodyCtasAreDistinctAndDeterministicAndLegacyTokensStayUnknown(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $contact = $this->createContact($audience, 'scoped@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $campaign = $this->createCampaign($audience);
        $renderer = $this->renderer(['body']);
        $body = '[Top](https://example.org/trip)'."\n\n".'[Bottom](https://example.org/trip)';
        $html = $renderer->html($audience, $contact, 'Two CTAs', $body, null, null, null, $campaign);
        self::assertSame($html, $renderer->html($audience, $contact, 'Two CTAs', $body, null, null, null, $campaign));
        $links = $this->trackedLinks($html);
        self::assertCount(2, $links);
        $tracker = self::getContainer()->get(ClickTracker::class);
        $decoded = array_map(static fn (string $url) => $tracker->decode(basename($url)), $links);
        self::assertSame('body', $decoded[0]?->scope);
        self::assertNotNull($decoded[0]->linkId);
        self::assertNotNull($decoded[1]);
        self::assertNotSame($decoded[0]->linkId, $decoded[1]->linkId);
        $changed = $this->trackedLinks($renderer->html($audience, $contact, 'Two CTAs', '[Top](https://example.org/another-trip)', null, null, null, $campaign));
        self::assertNotSame($decoded[0]->linkId, $tracker->decode(basename($changed[0]))?->linkId);

        $this->client->request(Request::METHOD_GET, $links[0]);
        self::assertResponseRedirects('https://example.org/trip');
        $event = $this->entityManager->getRepository(ClickEvent::class)->findOneBy(['campaign' => $campaign]);
        self::assertNotNull($event);
        self::assertSame($decoded[0]->linkId, $event->linkId);
        self::assertSame('body', $event->scope);

        $encoded = rtrim(strtr(base64_encode(json_encode(['c' => $contact->id, 'k' => $campaign->id, 'u' => 'https://example.org/legacy'], \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $payload = $encoded.'.'.substr(hash_hmac('sha256', 'click.'.$encoded, self::getContainer()->getParameter('kernel.secret')), 0, 32);
        $legacy = $tracker->decode($payload);
        self::assertNotNull($legacy);
        self::assertSame('body', $legacy->scope);
        self::assertNull($legacy->linkId);
    }

    public function testExplicitRolesAreOptInAndSystemAndUnsubscribeLinksRemainExcluded(): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = 'framed';
        $audience->clickTracking = true;
        $audience->utmSource = 'newsletter';
        $audience->headerMarkdown = '[Header](https://example.org/header)';
        $audience->footerMarkdown = '[Footer](https://example.org/footer)';

        $contact = $this->createContact($audience, 'roles@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $campaign = $this->createCampaign($audience);
        $body = '<a data-newsletter-link-id="trip-main" href="https://example.org/trip">Trip</a>'
            .'<a data-newsletter-scope="system" href="https://example.org/private">Private</a>';
        $unsubscribe = 'https://localhost.dev/newsletter/unsubscribe/'.$contact->token;
        $plain = $this->renderer(['body'])->html($audience, $contact, 'Roles', $body, null, $unsubscribe, null, $campaign);
        self::assertCount(1, $this->trackedLinks($plain));

        $html = $this->renderer(['body', 'navigation', 'footer'])->html($audience, $contact, 'Roles', $body, null, $unsubscribe, null, $campaign);
        $links = $this->trackedLinks($html);
        self::assertCount(3, $links);
        $tracker = self::getContainer()->get(ClickTracker::class);
        $roles = [];
        foreach ($links as $link) {
            $decoded = $tracker->decode(basename($link));
            self::assertNotNull($decoded);
            $roles[$decoded->scope] = $decoded;
        }

        self::assertSame('trip-main', $roles['body']->linkId);
        self::assertSame('https://example.org/footer', $roles['footer']->url);
        self::assertArrayHasKey('navigation', $roles);
        self::assertStringContainsString('href="'.$unsubscribe.'"', $html);
        self::assertStringContainsString('href="https://example.org/private"', $html);
        $contact->clickTrackingConsentAt = null;
        self::assertCount(0, $this->trackedLinks($this->renderer(['body', 'navigation', 'footer'])->html($audience, $contact, 'Roles', $body, null, $unsubscribe, null, $campaign)));
    }

    public function testBodyOnlySendDoesNotBecomeAZeroFooterMeasurement(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $contact = $this->createContact($audience, 'coverage@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Step']]);
        $message = self::getContainer()->get(NewsletterMailer::class)->sendStep($automation->getOrderedSteps()[0], $contact, 'Step', '[Read](https://example.org/trip)');
        self::assertSame(['body'], $message->trackingScopes);
        $query = new ReportQuery(new DateTimeImmutable('-1 day'), new DateTimeImmutable('+1 day'), $audience->id, groupBy: 'automation', clickScope: 'footer');
        $reports = array_values(iterator_to_array(self::getContainer()->get(LocalStatisticsProvider::class)->reports($query)));
        self::assertCount(1, $reports);
        self::assertSame(1, $reports[0]->metric('accepted')->value);
        self::assertSame(0, $reports[0]->metric('trackedMessages')->value);
        self::assertSame('notMeasured', $reports[0]->metric('clicks')->state);
        self::assertSame('notMeasured', $reports[0]->rate('clickRate')['state']);
    }

    /** @param list<string> $scopes */
    private function renderer(array $scopes): MailRenderer
    {
        $container = self::getContainer();
        $tracker = new ClickTracker($container->get(LinkGenerator::class), $container->get('router'), $container->getParameter('kernel.secret'), $scopes);

        return new MailRenderer($container->get(EmailMarkdownRenderer::class), $container->get(Environment::class), $container->get(SiteRegistry::class), $container->get(UtmDecorator::class), $tracker, $container->get(EmailTemplateRegistry::class));
    }

    /** @return list<string> */
    private function trackedLinks(string $html): array
    {
        return new Crawler($html)->filter('a[href*="/newsletter/c/"]')->each(static fn (Crawler $node): string => (string) $node->attr('href'));
    }
}
