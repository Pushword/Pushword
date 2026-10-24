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
use Pushword\Newsletter\Entity\EmailTemplate;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Trigger\PlaceholderRenderer;
use Pushword\Newsletter\Utm\UtmTag;
use Symfony\Component\DomCrawler\Crawler;

#[Group('integration')]
final class MailRendererTest extends AbstractNewsletterTestCase
{
    private const string UNSUBSCRIBE = 'https://localhost.dev/newsletter/unsubscribe/abcdef';

    public function testDefaultIdentityResolvesSiteNameAndYearInBothPartsAndCanBeCleared(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);
        $siteName = self::getContainer()->get(SiteRegistry::class)->get($audience->mainHost)->name;
        $copyright = '© '.new DateTimeImmutable()->format('Y').' '.$siteName;
        self::assertSame(Audience::DEFAULT_EMAIL_HEADER, $audience->headerMarkdown);
        self::assertSame(Audience::DEFAULT_EMAIL_FOOTER, $audience->footerMarkdown);

        $html = $renderer->html($audience, $contact, 'Subject', 'Body', null, self::UNSUBSCRIBE, null);
        self::assertStringContainsString($siteName, new Crawler($html)->filter('td')->first()->text());
        self::assertStringContainsString($copyright, new Crawler($html)->filter('td')->last()->text());
        $text = $renderer->text($audience, $contact, 'Body', self::UNSUBSCRIBE, SendContext::fromAudience($audience));
        self::assertStringContainsString($copyright, $text);
        self::assertStringNotContainsString('%siteName%', $html.$text);
        self::assertStringNotContainsString('%year%', $html.$text);

        $audience->headerMarkdown = '';
        $audience->footerMarkdown = '';
        self::assertSame('Body', $renderer->text($audience, $contact, 'Body', null));
        self::assertStringNotContainsString($copyright, $renderer->html($audience, $contact, 'Subject', 'Body', null, null, null));
    }

    public function testPersonalMailIgnoresNewsletterIdentityAndOccurrenceFooterWithOptionalSignature(): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = 'personal';
        $audience->postalAddress = '12 Baker Street';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);
        $context = new SendContext('localhost.dev', 'mail@example.tld', 'Brand', footerMarkdown: 'Newsletter footer');
        foreach (['', '**Robin** [Contact](/contact)'] as $signature) {
            $audience->signatureMarkdown = $signature;
            $html = $renderer->html($audience, $contact, 'Subject', 'Hello %name%.', 'Hidden teaser', self::UNSUBSCRIBE, null, sendContext: $context);
            $crawler = new Crawler($html);
            self::assertCount(0, $crawler->filter('table, hr'));
            self::assertSame('en', $crawler->filter('html')->attr('lang'));
            foreach (['12 Baker Street', 'Newsletter footer', 'Hidden teaser', self::UNSUBSCRIBE, '©'] as $decoration) {
                self::assertStringNotContainsString($decoration, $html);
            }

            self::assertSame('Hello Test.'.('' === $signature ? '' : "\n\n".$signature), $renderer->text($audience, $contact, 'Hello %name%.', self::UNSUBSCRIBE, $context));
            self::assertCount('' === $signature ? 0 : 1, $crawler->filter('a[href="https://localhost.dev/contact"]'));
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function layouts(): iterable
    {
        yield 'without a frame' => [false];
        yield 'with a frame' => [true];
    }

    #[DataProvider('layouts')]
    public function testAudienceDesignWrapsBothPartsAndKeepsTheUnsubscribeLink(bool $wrapped): void
    {
        $audience = $this->createAudience();
        $audience->emailWrapped = $wrapped;
        $audience->headerMarkdown = '## Brand name';
        $audience->footerMarkdown = '[Contact](/contact)';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);

        $html = $renderer->html($audience, $contact, 'Subject', 'Hello **%name%**.', null, self::UNSUBSCRIBE, null);
        $crawler = new Crawler($html);
        self::assertCount($wrapped ? 2 : 1, $crawler->filter('table[role="presentation"]'));
        self::assertSame('Brand name', $crawler->filter('h2')->text());
        self::assertStringContainsString('Hello Test.', $crawler->filter('body')->text());
        self::assertCount(1, $crawler->filter('a[href="https://localhost.dev/contact"]'));
        self::assertCount(1, $crawler->filter('a[href="'.self::UNSUBSCRIBE.'"]'));
        self::assertStringContainsString('Brand name', $renderer->text($audience, $contact, 'Hello %name%.', self::UNSUBSCRIBE));
        self::assertStringContainsString('[Contact](/contact)', $renderer->text($audience, $contact, 'Hello %name%.', self::UNSUBSCRIBE));
    }

    public function testAnOccurrenceFooterOverridesTheAudienceFooter(): void
    {
        $audience = $this->createAudience();
        $audience->footerMarkdown = 'Audience signature';
        self::assertSame('Audience signature', SendContext::fromAudience($audience)->footerMarkdown);
        $contact = $this->createContact($audience, 'reader@example.tld');
        $context = new SendContext('localhost.dev', 'mail@example.tld', 'Brand', footerMarkdown: 'Local signature');
        $renderer = self::getContainer()->get(MailRenderer::class);
        $html = $renderer->html($audience, $contact, 'Subject', 'Body', null, null, null, sendContext: $context);

        self::assertStringContainsString('Local signature', $html);
        self::assertStringNotContainsString('Audience signature', $html);
        self::assertStringContainsString('Local signature', $renderer->text($audience, $contact, 'Body', null, $context));
        self::assertStringNotContainsString('Audience signature', $renderer->text($audience, $contact, 'Body', null, $context));
    }

    public function testASavedDesignResolvesBothPartsAndPreservesExplicitOccurrenceFooters(): void
    {
        $audience = $this->createAudience();
        $audience->headerMarkdown = 'Unused audience header';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $template = new EmailTemplate();
        $template->name = 'render-template-'.bin2hex(random_bytes(6));
        $template->headerMarkdown = '## Saved %siteName%';
        $template->footerMarkdown = 'Saved copyright %year%';

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        try {
            $audience->emailLayout = 'saved:'.$template->id;
            $renderer = self::getContainer()->get(MailRenderer::class);
            $context = SendContext::fromAudience($audience);
            self::assertNull($context->footerMarkdown);
            self::assertSame($context->toArray(), SendContext::fromArray($context->toArray())->toArray());
            $html = $renderer->html($audience, $contact, 'Subject', 'Body', null, self::UNSUBSCRIBE, null, sendContext: $context);
            $text = $renderer->text($audience, $contact, 'Body', self::UNSUBSCRIBE, $context);
            self::assertStringContainsString('Saved Pushword', $html.$text);
            self::assertStringContainsString('Saved copyright '.new DateTimeImmutable()->format('Y'), $html);
            self::assertStringContainsString('Saved copyright '.new DateTimeImmutable()->format('Y'), $text);
            self::assertStringNotContainsString('Unused audience header', $html.$text);

            foreach (['Occurrence signature', ''] as $footer) {
                $override = new SendContext('localhost.dev', 'mail@example.tld', 'Brand', footerMarkdown: $footer);
                $html = $renderer->html($audience, $contact, 'Subject', 'Body', null, null, null, sendContext: $override);
                $text = $renderer->text($audience, $contact, 'Body', null, $override);
                self::assertStringNotContainsString('Saved copyright', $html.$text);
                if ('' !== $footer) {
                    self::assertStringContainsString($footer, $html);
                    self::assertStringContainsString($footer, $text);
                }
            }

            $template->wrapped = false;
            $template->headerMarkdown = '';
            $template->footerMarkdown = '';
            $html = $renderer->html($audience, $contact, 'Subject', 'Body', null, null, null);
            self::assertCount(1, new Crawler($html)->filter('table[role="presentation"]'));
            self::assertSame('Body', $renderer->text($audience, $contact, 'Body', null));
            $audience->emailLayout = 'simple';
            self::assertNull($audience->emailTemplate);
            self::assertStringContainsString('Unused audience header', $renderer->html($audience, $contact, 'Subject', 'Body', null, null, null));
        } finally {
            $this->entityManager->remove($template);
            $this->entityManager->flush();
        }
    }

    /** @return iterable<string, array{string, int}> */
    public static function twigPresets(): iterable
    {
        yield 'classic' => ['pushword', 2];
        yield 'letter' => ['pushword_letter', 1];
        yield 'bulletin' => ['pushword_bulletin', 3];
    }

    #[DataProvider('twigPresets')]
    public function testATwigPresetControlsTheDesignWithoutLosingTheEditableLayout(string $preset, int $tables): void
    {
        $audience = $this->createAudience();
        $audience->headerMarkdown = 'Editable brand';
        $audience->footerMarkdown = 'Editable signature';
        $audience->postalAddress = '12 Baker Street';
        $audience->emailLayout = 'twig:'.$preset;

        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);
        $context = SendContext::fromAudience($audience);
        $html = $renderer->html($audience, $contact, 'Subject', 'Hello **%name%**.', null, self::UNSUBSCRIBE, null, sendContext: $context);
        $crawler = new Crawler($html);

        self::assertCount($tables, $crawler->filter('table[role="presentation"]'));
        self::assertStringContainsString('Pushword', $crawler->filter('td')->first()->text());
        self::assertStringContainsString('Hello Test.', $crawler->filter('body')->text());
        self::assertStringContainsString($audience->name, $crawler->filter('td')->last()->text());
        self::assertStringContainsString('© '.new DateTimeImmutable()->format('Y').' Pushword', $crawler->filter('td')->last()->text());
        self::assertStringContainsString('12 Baker Street', $html);
        self::assertCount(1, $crawler->filter('a[href="'.self::UNSUBSCRIBE.'"]'));
        self::assertStringNotContainsString('Editable', $html);
        $text = $renderer->text($audience, $contact, 'Hello %name%.', self::UNSUBSCRIBE, $context);
        self::assertStringNotContainsString('Editable', $text);
        self::assertStringContainsString('Hello Test.', $text);
        self::assertStringContainsString('12 Baker Street', $text);
        self::assertStringContainsString(self::UNSUBSCRIBE, $text);
        self::assertStringNotContainsString('Editable', $renderer->text($audience, $contact, 'Body', null));
        $override = new SendContext('localhost.dev', 'mail@example.tld', 'Brand', footerMarkdown: 'Occurrence signature');
        self::assertStringContainsString('Occurrence signature', $renderer->text($audience, $contact, 'Body', null, $override));

        $audience->emailLayout = 'simple';
        self::assertNull($audience->emailTemplate);
        self::assertFalse($audience->emailWrapped);
        $html = $renderer->html($audience, $contact, 'Subject', 'Body', null, null, null);
        self::assertStringContainsString('Editable brand', $html);
        self::assertStringContainsString('Editable signature', $html);
    }

    public function testEmailAddressesInBodyAndFooterNeedNoJavaScriptAndSensitiveLinksStayDirect(): void
    {
        $audience = $this->createAudience();
        $audience->headerMarkdown = '';
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
        $audience->headerMarkdown = '';

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
        $audience->emailWrapped = true;
        $audience->footerMarkdown = 'Service signature';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $renderer = self::getContainer()->get(MailRenderer::class);

        $html = $renderer->html($audience, $contact, 'Subject', 'Your order is on its way.', null, null, null);
        $text = $renderer->text($audience, $contact, 'Your order is on its way.', null);

        self::assertStringNotContainsString('Unsubscribe', $html);
        self::assertStringContainsString('12 Baker Street', $html);
        self::assertStringContainsString('12 Baker Street', $text);
        self::assertStringContainsString('Service signature', $html);
        self::assertStringContainsString('Service signature', $text);
    }

    /** An audience with neither has no foot to draw, and no rule to draw it under. */
    public function testAMailWithNoFootAtAllDrawsNoSeparator(): void
    {
        $audience = $this->createAudience();
        $audience->headerMarkdown = '';
        $audience->footerMarkdown = '';

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
