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

    public function testSeveralAudiencesBecomeTickedCheckboxes(): void
    {
        $letter = $this->createAudience();
        $promos = $this->createAudience();

        $html = $this->extension()->renderForm([$letter->getSlug(), $promos->getSlug()]);

        self::assertStringContainsString('name="audiences[]" value="'.$letter->getSlug().'" checked', $html);
        self::assertStringContainsString('name="audiences[]" value="'.$promos->getSlug().'" checked', $html);
        self::assertStringNotContainsString('name="audience"', $html, 'the single-audience hidden field would post a list nobody chose');
    }

    /** An interest one list declares must not be posted as if the other one knew it. */
    public function testInterestsSurviveWhenAnyOfferedAudienceDeclaresThem(): void
    {
        $withInterest = $this->createAudience(interests: ['AmTrek']);
        $without = $this->createAudience();

        $html = $this->extension()->renderForm([$without->getSlug(), $withInterest->getSlug()], ['AmTrek', 'Undeclared']);

        self::assertStringContainsString('name="interests[]" value="AmTrek"', $html);
        self::assertStringNotContainsString('Undeclared', $html);
    }

    /** A slug that matches nothing is left out, and one survivor is a form, not a choice. */
    public function testOnlyTheAudiencesThatExistAreOffered(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm([$audience->getSlug(), 'does-not-exist']);

        self::assertStringContainsString('name="audience" value="'.$audience->getSlug().'"', $html);
        self::assertStringNotContainsString('does-not-exist', $html);
        self::assertStringNotContainsString('type="checkbox"', $html);
    }

    public function testAnUnknownAudienceRendersNothing(): void
    {
        self::assertSame('', $this->extension()->renderForm('does-not-exist'));
        self::assertSame('', $this->extension()->renderForm(['does-not-exist', 'neither-does-this']));
    }
}
