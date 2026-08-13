<?php

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Utm\UtmTag;

#[Group('integration')]
final class MailRendererTest extends AbstractNewsletterTestCase
{
    private const string UNSUBSCRIBE = 'https://localhost.dev/newsletter/unsubscribe/abcdef';

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
