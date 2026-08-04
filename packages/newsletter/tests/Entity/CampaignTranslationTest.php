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
