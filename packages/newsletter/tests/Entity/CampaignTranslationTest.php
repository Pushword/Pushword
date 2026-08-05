<?php

namespace Pushword\Newsletter\Tests\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Entity\Campaign;

/**
 * The three-step fallback: the locale as the contact carries it, then its
 * language part, then the campaign's own text. A reader never gets an empty
 * mail, whatever is missing.
 */
final class CampaignTranslationTest extends TestCase
{
    #[DataProvider('resolutionProvider')]
    public function testTheContentResolvesThroughTheFallbackChain(string $locale, string $expected): void
    {
        $campaign = $this->campaign();

        self::assertSame($expected, $campaign->contentFor($locale)['subject']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function resolutionProvider(): iterable
    {
        yield 'exact locale' => ['de', 'Hallo'];
        yield 'language part of a regional locale' => ['de-ch', 'Hallo'];
        yield 'regional locale, written out' => ['pt-br', 'Olá do Brasil'];
        yield 'underscore spelling' => ['de_CH', 'Hallo'];
        yield 'case' => ['DE', 'Hallo'];
        yield 'untranslated language' => ['it', 'Hello'];
        yield 'no locale at all' => ['', 'Hello'];
    }

    /** A regional translation wins over the language it belongs to. */
    public function testTheMoreSpecificLocaleWins(): void
    {
        $campaign = $this->campaign();

        self::assertSame('Olá do Brasil', $campaign->contentFor('pt-br')['subject']);
        self::assertSame('Olá', $campaign->contentFor('pt')['subject']);
    }

    /** Each field falls back on its own: a translated subject with no body is allowed. */
    public function testAPartialTranslationOnlyReplacesWhatItWrites(): void
    {
        $campaign = $this->campaign();
        $campaign->translations = ['fr' => ['subject' => 'Bonjour']];

        $content = $campaign->contentFor('fr');

        self::assertSame('Bonjour', $content['subject']);
        self::assertSame('Read this.', $content['bodyMarkdown']);
        self::assertSame('The preview line.', $content['preheader']);
    }

    /** An opened-and-left-alone locale would otherwise mail an empty body. */
    public function testBlankFieldsAreNotStored(): void
    {
        $campaign = $this->campaign();
        $campaign->translations = [
            'fr' => ['subject' => '  ', 'bodyMarkdown' => "\n"],
            'es' => ['subject' => 'Hola'],
            '' => ['subject' => 'Nowhere'],
        ];

        self::assertSame(['es' => ['subject' => 'Hola']], $campaign->translations);
        self::assertSame('Hello', $campaign->contentFor('fr')['subject']);
    }

    public function testLocalesAreStoredNormalised(): void
    {
        $campaign = new Campaign();
        $campaign->translations = ['DE_ch' => ['subject' => 'Hallo'], 'FR' => ['subject' => 'Bonjour']];

        self::assertSame(['de-ch', 'fr'], $campaign->translatedLocales());
    }

    /** Writing the German subject alone must not send it over the default body. */
    public function testMergingWritesOnlyTheFieldsItNames(): void
    {
        $campaign = $this->campaign();

        $campaign->mergeTranslations(['de' => ['subject' => 'Hallo!']]);

        self::assertSame(['subject' => 'Hallo!', 'bodyMarkdown' => 'Lies das.'], $campaign->translations['de']);
        self::assertSame('Lies das.', $campaign->contentFor('de')['bodyMarkdown']);
        self::assertArrayHasKey('pt', $campaign->translations, 'a locale left out is kept');
    }

    /** The locale is addressed the way it is stored, not the way it was typed. */
    public function testMergingNormalisesTheLocaleItAddresses(): void
    {
        $campaign = $this->campaign();

        $campaign->mergeTranslations(['DE_ch' => ['subject' => 'Hallo'], 'PT' => ['bodyMarkdown' => 'Leia isto.']]);

        self::assertSame(['subject' => 'Olá', 'bodyMarkdown' => 'Leia isto.'], $campaign->translations['pt']);
        self::assertSame(['de', 'de-ch', 'pt', 'pt-br'], $campaign->translatedLocales());
    }

    /** A blank field is how the merge takes one back; null is how it takes a locale back. */
    public function testMergingClearsABlankedFieldAndDropsANulledLocale(): void
    {
        $campaign = $this->campaign();

        $campaign->mergeTranslations(['de' => ['bodyMarkdown' => ''], 'pt-br' => null]);

        self::assertSame(['subject' => 'Hallo'], $campaign->translations['de']);
        self::assertSame('Read this.', $campaign->contentFor('de')['bodyMarkdown']);
        self::assertSame(['de', 'pt'], $campaign->translatedLocales());

        $campaign->mergeTranslations(['de' => ['subject' => '  ']]);

        self::assertSame(['pt'], $campaign->translatedLocales(), 'a locale left with no field is dropped');
    }

    public function testMergingIgnoresWhatIsNeitherAnEntryNorADrop(): void
    {
        $campaign = $this->campaign();

        $campaign->mergeTranslations(['de' => 'Hallo', '' => ['subject' => 'Nowhere'], 'it' => ['subject' => 42]]);

        self::assertSame(['subject' => 'Hallo', 'bodyMarkdown' => 'Lies das.'], $campaign->translations['de']);
        self::assertSame(['de', 'pt', 'pt-br'], $campaign->translatedLocales());
    }

    public function testTheDefaultTextIsWhatAnUntranslatedCampaignSends(): void
    {
        $campaign = new Campaign();
        $campaign->subject = 'Hello';
        $campaign->bodyMarkdown = 'Read this.';

        self::assertSame(
            ['subject' => 'Hello', 'preheader' => null, 'bodyMarkdown' => 'Read this.'],
            $campaign->contentFor('de'),
        );
    }

    private function campaign(): Campaign
    {
        $campaign = new Campaign();
        $campaign->subject = 'Hello';
        $campaign->preheader = 'The preview line.';
        $campaign->bodyMarkdown = 'Read this.';
        $campaign->translations = [
            'de' => ['subject' => 'Hallo', 'bodyMarkdown' => 'Lies das.'],
            'pt' => ['subject' => 'Olá'],
            'pt-br' => ['subject' => 'Olá do Brasil'],
        ];

        return $campaign;
    }
}
