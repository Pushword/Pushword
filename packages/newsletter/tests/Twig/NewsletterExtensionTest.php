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

    public function testSeveralAudiencesTravelHidden(): void
    {
        $letter = $this->createAudience();
        $promos = $this->createAudience();

        $html = $this->extension()->renderForm([$letter->getSlug(), $promos->getSlug()]);

        self::assertStringContainsString('type="hidden" name="audiences[]" value="'.$letter->getSlug().'"', $html);
        self::assertStringContainsString('type="hidden" name="audiences[]" value="'.$promos->getSlug().'"', $html);
        self::assertStringNotContainsString('name="audience"', $html, 'the single-audience field would post one list out of the two');
    }

    /**
     * js-helper binds `.live-form` on every DOMChanged, which is what makes the
     * form work when it is itself loaded dynamically. An inline <script> would
     * not: one injected through innerHTML never runs.
     */
    public function testTheFormIsALiveFormRatherThanCarryingItsOwnScript(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm($audience->getSlug());

        self::assertStringContainsString('class="live-form"', $html);
        self::assertStringNotContainsString('<script', $html);
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

    /** A slug that matches nothing is left out, and one survivor posts as one list. */
    public function testOnlyTheAudiencesThatExistAreOffered(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm([$audience->getSlug(), 'does-not-exist']);

        self::assertStringContainsString('name="audience" value="'.$audience->getSlug().'"', $html);
        self::assertStringNotContainsString('does-not-exist', $html);
        self::assertStringNotContainsString('name="audiences[]"', $html);
    }

    public function testAnUnknownAudienceRendersNothing(): void
    {
        self::assertSame('', $this->extension()->renderForm('does-not-exist'));
        self::assertSame('', $this->extension()->renderForm(['does-not-exist', 'neither-does-this']));
    }
}
