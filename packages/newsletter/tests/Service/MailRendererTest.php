<?php

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
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
        $audience->setUtmSource('newsletter');

        $html = $this->render($audience, '[Read](/article)', new UtmTag('janvier'));

        self::assertStringContainsString('https://localhost.dev/article?utm_source=newsletter', $html);
        self::assertStringContainsString('href="'.self::UNSUBSCRIBE.'"', $html);
    }

    private function render(Audience $audience, string $bodyMarkdown, ?UtmTag $utmTag): string
    {
        $contact = $this->createContact($audience, 'reader@example.tld');

        return self::getContainer()->get(MailRenderer::class)
            ->html($audience, $contact, 'Subject', $bodyMarkdown, null, self::UNSUBSCRIBE, $utmTag);
    }
}
