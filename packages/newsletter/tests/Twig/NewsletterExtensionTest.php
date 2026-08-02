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

    /**
     * The call may run at build time on a statically generated site, so it can
     * only leave an address behind: the form is fetched per visitor.
     */
    public function testTheCallLeavesAPlaceholderPointingAtTheLiveHost(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm($audience->slug);

        self::assertStringContainsString('data-live="https://localhost.dev/newsletter/form?', $html);
        self::assertStringContainsString('audiences='.$audience->slug, $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testSeveralAudiencesTravelInOneAddress(): void
    {
        $letter = $this->createAudience();
        $promos = $this->createAudience();

        $html = $this->extension()->renderForm([$letter->slug, $promos->slug]);

        self::assertStringContainsString('audiences='.$letter->slug.'%2C'.$promos->slug, $html);
    }

    public function testOnlyDeclaredInterestsReachTheAddress(): void
    {
        $audience = $this->createAudience(interests: ['AmTrek']);

        $html = $this->extension()->renderForm($audience->slug, ['AmTrek', 'Undeclared']);

        self::assertStringContainsString('interests=AmTrek', $html);
        self::assertStringNotContainsString('Undeclared', $html);
    }

    /** An interest one list declares must not be posted as if the other one knew it. */
    public function testInterestsSurviveWhenAnyOfferedAudienceDeclaresThem(): void
    {
        $withInterest = $this->createAudience(interests: ['AmTrek']);
        $without = $this->createAudience();

        $html = $this->extension()->renderForm([$without->slug, $withInterest->slug], ['AmTrek', 'Undeclared']);

        self::assertStringContainsString('interests=AmTrek', $html);
        self::assertStringNotContainsString('Undeclared', $html);
    }

    /** A slug that matches nothing is left out rather than asked for. */
    public function testOnlyTheAudiencesThatExistAreOffered(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm([$audience->slug, 'does-not-exist']);

        self::assertStringContainsString('audiences='.$audience->slug.'&', $html);
        self::assertStringNotContainsString('does-not-exist', $html);
    }

    public function testTheSourceNamesWhereTheFormSits(): void
    {
        $audience = $this->createAudience();

        $html = $this->extension()->renderForm($audience->slug, [], 'footer');

        self::assertStringContainsString('source=footer', $html);
    }

    public function testAnUnknownAudienceRendersNothing(): void
    {
        self::assertSame('', $this->extension()->renderForm('does-not-exist'));
        self::assertSame('', $this->extension()->renderForm(['does-not-exist', 'neither-does-this']));
    }
}
