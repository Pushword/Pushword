<?php

namespace Pushword\Newsletter\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Twig\NewsletterExtension;

#[Group('integration')]
final class NewsletterExtensionTest extends AbstractNewsletterTestCase
{
    private function extension(): NewsletterExtension
    {
        return self::getContainer()->get(NewsletterExtension::class);
    }

    public function testTheFormPostsToTheLiveHostSoAStaticPageCanUseIt(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm($audience->getSlug());

        self::assertStringContainsString('action="https://localhost.dev/newsletter/subscribe"', $html);
        self::assertStringContainsString('name="audience" value="'.$audience->getSlug().'"', $html);
        self::assertStringContainsString('name="email"', $html);
        self::assertStringContainsString('name="name"', $html);
    }

    public function testTheHoneypotIsShippedAndHidden(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm($audience->getSlug());

        self::assertStringContainsString('name="website"', $html);
        self::assertMatchesRegularExpression('/aria-hidden="true"[^>]*style="[^"]*-9999px/', $html);
    }

    public function testOnlyDeclaredInterestsBecomeHiddenFields(): void
    {
        $audience = $this->createAudience(interests: ['AmTrek']);

        $html = $this->extension()->renderForm($audience->getSlug(), ['AmTrek', 'Undeclared']);

        self::assertStringContainsString('name="interests[]" value="AmTrek"', $html);
        self::assertStringNotContainsString('Undeclared', $html);
    }

    public function testAnUnknownAudienceRendersNothing(): void
    {
        self::assertSame('', $this->extension()->renderForm('does-not-exist'));
    }
}
