<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Trigger\PlaceholderRenderer;
use Pushword\Newsletter\Utm\UtmTag;
use Symfony\Component\DomCrawler\Crawler;

#[Group('integration')]
final class MailRendererTest extends AbstractNewsletterTestCase
{
    private const string UNSUBSCRIBE = 'https://localhost.dev/newsletter/unsubscribe/abcdef';

    public function testEmailAddressesInBodyAndFooterNeedNoJavaScriptAndSensitiveLinksStayDirect(): void
    {
        $audience = $this->createAudience();
        $audience->utmSource = 'newsletter';
        $audience->clickTracking = true;

        $contact = $this->createContact($audience, 'reader@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $campaign = $this->createCampaign($audience);
        $this->entityManager->flush();
        $url = 'https://brand.example.test/resume?token=secret&next=/booking';
        $context = new SendContext(
            'localhost.dev',
            'newsletter@example.test',
            'Brand',
            footerMarkdown: 'Contact info@example.test or [Write](mailto:support@example.test). [Resume]('.$url.')',
        );
        $body = 'Contact info@example.test or <help@example.test> or [support@example.test](mailto:support@example.test).'
            .' [Resume]('.$url.') [Article](/article)';
        $renderer = self::getContainer()->get(MailRenderer::class);
        $html = $renderer->html(
            $audience,
            $contact,
            'Subject',
            $body,
            null,
            self::UNSUBSCRIBE,
            new UtmTag('reminder'),
            $campaign,
            $context,
            untrackedUrls: [$url],
        );
        $crawler = new Crawler($html);

        self::assertCount(2, $crawler->filter('a[href="mailto:info@example.test"]'));
        self::assertCount(1, $crawler->filter('a[href="mailto:help@example.test"]'));
        self::assertCount(2, $crawler->filter('a[href="mailto:support@example.test"]'));
        self::assertStringContainsString('info@example.test', $crawler->filter('td')->first()->text());
        self::assertStringContainsString('info@example.test', $crawler->filter('td')->last()->text());
        self::assertCount(2, $crawler->filter('a')->reduce(static fn (Crawler $link): bool => $url === $link->attr('href')));
        self::assertStringContainsString('/newsletter/c/', $html, 'ordinary links still follow the tracking pipeline');
        self::assertStringNotContainsString('data-rot', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString(str_rot13('info@example.test'), $html);
        self::assertStringContainsString('info@example.test', $renderer->text($audience, $contact, $body, self::UNSUBSCRIBE, $context));

        // Rendering mail must not disable the web parser's obfuscation, including cached output.
        $web = self::getContainer()->get(MarkdownParser::class)->transform('Contact info@example.test.');
        self::assertStringContainsString(str_rot13('info@example.test'), $web);
        self::assertStringNotContainsString('info@example.test', $web);
    }

    /** @return iterable<string, array{string}> */
    public static function optionalImages(): iterable
    {
        yield 'absolute JPEG' => ['![Mountain & lake](https://admin-block-editor.test/images/trip.jpg "Trip photo")'];
        yield 'JPEG with fixed dimensions' => ['![Mountain & lake](https://admin-block-editor.test/images/trip.jpg "Trip photo"){width="1200" height="800"}'];
        yield 'no image' => [''];
    }

    #[DataProvider('optionalImages')]
    public function testAnOptionalImagePlaceholderRendersBeforeTheGreetingWithoutWebMediaMarkup(string $image): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $context = new SendContext('admin-block-editor.test', 'newsletter@example.test', 'Brand', footerMarkdown: $image);
        $body = self::getContainer()->get(PlaceholderRenderer::class)
            ->render("{{ content.image }}\n\nHello **%name%**.", ['content.image' => $image]);
        $html = self::getContainer()->get(MailRenderer::class)
            ->html($audience, $contact, 'Subject', $body, null, self::UNSUBSCRIBE, null, sendContext: $context);
        $crawler = new Crawler($html);
        $images = $crawler->filter('img');

        self::assertCount('' === $image ? 0 : 2, $images);
        if ('' !== $image) {
            foreach ($images as $element) {
                $img = new Crawler($element);
                self::assertSame('https://admin-block-editor.test/images/trip.jpg', $img->attr('src'));
                self::assertSame('Mountain & lake', $img->attr('alt'));
                self::assertSame('Trip photo', $img->attr('title'));
                self::assertSame('100%', $img->attr('width'));
                self::assertSame('display:block;width:100%;max-width:100%;height:auto;', $img->attr('style'));
                self::assertNull($img->attr('height'));
                self::assertNull($img->attr('loading'));
                self::assertNull($img->attr('srcset'));
            }

            self::assertLessThan(strpos($html, 'Hello'), strpos($html, '<img'));
        } else {
            self::assertSame('Hello Test.', $crawler->filter('td')->first()->text());
        }

        self::assertCount(0, $crawler->filter('picture, source, script, a img'));
        self::assertStringNotContainsString('data-', $html);
        self::assertStringNotContainsString('<!--', $html);
        self::assertStringNotContainsString('<p></p>', $html);
        self::assertStringNotContainsString('{{ content.image }}', $html);
    }

    /** A `/slug` link has nothing to resolve against once it is in an inbox. */
    public function testARootRelativeLinkIsMadeAbsolute(): void
    {
        $html = $this->render($this->createAudience(), '[Read](/article)', null);

        self::assertStringContainsString('href="https://localhost.dev/article"', $html);
    }

    public function testAnAlreadyAbsoluteLinkIsLeftAlone(): void
    {
        $html = $this->render($this->createAudience(), '[Out](https://example.com/x)', null);

        self::assertStringContainsString('href="https://example.com/x"', $html);
    }

    public function testTheBodyIsTaggedButTheUnsubscribeLinkIsNot(): void
    {
        $audience = $this->createAudience();
        $audience->utmSource = 'newsletter';

        $html = $this->render($audience, '[Read](/article)', new UtmTag('janvier'));

        self::assertStringContainsString('https://localhost.dev/article?utm_source=newsletter', $html);
        self::assertStringContainsString('href="'.self::UNSUBSCRIBE.'"', $html);
    }

    /**
     * A transactional mail offers no way off a list it did not put anybody on.
     * The postal address is not part of that: it says who wrote, not how to
     * leave, so it stays in both parts of the mail.
     */
    public function testATransactionalMailCarriesNoUnsubscribeFootButKeepsTheAddress(): void
    {
        $audience = $this->createAudience();
        $audience->postalAddress = "Test Publishing\n12 Baker Street";

        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);

        $html = $renderer->html($audience, $contact, 'Subject', 'Your order is on its way.', null, null, null);
        $text = $renderer->text($audience, $contact, 'Your order is on its way.', null);

        self::assertStringNotContainsString('Unsubscribe', $html);
        self::assertStringContainsString('12 Baker Street', $html);
        self::assertStringContainsString('12 Baker Street', $text);
    }

    /** An audience with neither has no foot to draw, and no rule to draw it under. */
    public function testAMailWithNoFootAtAllDrawsNoSeparator(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);

        $text = $renderer->text($audience, $contact, 'Your order is on its way.', null);

        self::assertSame('Your order is on its way.', $text);
        self::assertStringNotContainsString('<hr', $renderer->html($audience, $contact, 'Subject', 'Your order is on its way.', null, null, null));
    }

    public function testTheConfirmationButtonWearsTheHostsPrimaryColor(): void
    {
        self::getContainer()->get(SiteRegistry::class)->get('localhost.dev')
            ->setCustomProperty('css_var:color_primary', '#92400e');

        self::assertStringContainsString('background-color:#92400e', $this->confirmation());
    }

    public function testWithoutAPrimaryColorTheConfirmationButtonKeepsItsDefault(): void
    {
        self::assertStringContainsString('background-color:#1c1c1c', $this->confirmation());
    }

    private function confirmation(): string
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld', subscribed: false);

        return self::getContainer()->get(MailRenderer::class)
            ->confirmationHtml($audience, $contact, 'Subject', 'https://localhost.dev/newsletter/confirm/'.str_repeat('a', 64));
    }

    private function render(Audience $audience, string $bodyMarkdown, ?UtmTag $utmTag): string
    {
        $contact = $this->createContact($audience, 'reader@example.tld');

        return self::getContainer()->get(MailRenderer::class)
            ->html($audience, $contact, 'Subject', $bodyMarkdown, null, self::UNSUBSCRIBE, $utmTag);
    }
}
