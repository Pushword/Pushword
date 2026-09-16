<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\EmbeddedRights;
use Pushword\Core\Image\License\MediaLicense;

final class MediaLicenseTest extends TestCase
{
    /**
     * xmpRights:WebStatement commonly holds a bare hostname, which both UrlField and
     * schema.org reject — an editor opening such a media and saving without changing
     * anything would get a validation error.
     */
    #[DataProvider('urlProvider')]
    public function testUrlNormalization(string $raw, string $expected): void
    {
        self::assertSame($expected, MediaLicense::normalizeUrl($raw));
    }

    /** @return iterable<string, array{string, string}> */
    public static function urlProvider(): iterable
    {
        yield 'bare hostname' => ['www.enricoromanzi.it', 'https://www.enricoromanzi.it'];
        yield 'already absolute' => ['https://example.tld/terms', 'https://example.tld/terms'];
        yield 'http kept as-is' => ['http://example.tld', 'http://example.tld'];
        yield 'protocol relative' => ['//example.tld/terms', 'https://example.tld/terms'];
        yield 'surrounding spaces' => ['  example.tld  ', 'https://example.tld'];
        yield 'prose is not a url' => ['All rights reserved', ''];
        yield 'no dot is not a host' => ['localhost', ''];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('digitalSourceTypeProvider')]
    public function testDigitalSourceTypeNormalization(string $raw, string $expected): void
    {
        self::assertSame($expected, MediaLicense::normalizeDigitalSourceType($raw));
    }

    /** @return iterable<string, array{string, string}> */
    public static function digitalSourceTypeProvider(): iterable
    {
        $prefix = MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX;

        yield 'bare upper camel token' => ['TrainedAlgorithmicMedia', $prefix.'trainedAlgorithmicMedia'];
        yield 'canonical uri' => [$prefix.'trainedAlgorithmicMedia', $prefix.'trainedAlgorithmicMedia'];
        yield 'canonical casing kept' => ['digitalCapture', $prefix.'digitalCapture'];
        yield 'unknown token still prefixed' => ['SomethingNew', $prefix.'somethingNew'];
        yield 'empty' => ['', ''];
    }

    /**
     * The marker must match exactly: a substring search for "AI" would swallow an
     * agency called "AI Generated Studio Ltd" and silently erase its credit.
     */
    #[DataProvider('generatorCreditProvider')]
    public function testGeneratorCreditDetection(string $credit, bool $expected): void
    {
        self::assertSame($expected, MediaLicense::isGeneratorCredit($credit));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function generatorCreditProvider(): iterable
    {
        yield 'chatgpt' => ['AI Generated', true];
        yield 'gemini' => ['Made with Google AI', true];
        yield 'case and spacing' => ['  ai generated ', true];
        yield 'a real agency name' => ['AI Generated Studio Ltd', false];
        yield 'a real photographer' => ['O2Ephotos', false];
        yield 'empty' => ['', false];
    }

    public function testGeneratorCreditIsStrippedAndRecordedAsProvenance(): void
    {
        $stripped = new EmbeddedRights(creditText: 'AI Generated')->stripGeneratorMarkers();

        self::assertSame('', $stripped->creditText);
        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
            $stripped->digitalSourceType,
        );
        // Provenance is not a rights claim, so the site may still license the image.
        self::assertFalse($stripped->hasRightsValue());
    }

    public function testStructuralMarkerAloneNeedsNoCreditLine(): void
    {
        $rights = new EmbeddedRights(
            digitalSourceType: MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
        )->stripGeneratorMarkers();

        self::assertFalse($rights->hasRightsValue());
        self::assertNotSame('', $rights->digitalSourceType);
    }

    public function testARealCreditLineIsNeverStripped(): void
    {
        $rights = new EmbeddedRights(creditText: 'AI Generated Studio Ltd')->stripGeneratorMarkers();

        self::assertSame('AI Generated Studio Ltd', $rights->creditText);
        self::assertTrue($rights->hasRightsValue());
    }

    /** Provenance is recorded, but a real by-line still gates the seeding decision. */
    public function testGeneratorMarkerDoesNotExcuseThirdPartyAuthorship(): void
    {
        $rights = new EmbeddedRights(
            creditText: 'AI Generated',
            creator: ['Enrico Romanzi'],
        )->stripGeneratorMarkers();

        self::assertSame('', $rights->creditText);
        self::assertTrue($rights->hasRightsValue());
    }

    /**
     * acquireLicensePage is a rights claim for the seeding gate but not enough for
     * Google — the two lists are deliberately different.
     */
    public function testAcquireLicensePageGatesSeedingButNotEmission(): void
    {
        self::assertContains(MediaLicense::ACQUIRE_LICENSE_PAGE, MediaLicense::RIGHTS_KEYS);
        self::assertNotContains(MediaLicense::ACQUIRE_LICENSE_PAGE, MediaLicense::GOOGLE_MINIMUM_KEYS);
    }

    public function testSeedNormalizationKeepsOnlyWhatAnAppMaySeed(): void
    {
        $seed = MediaLicense::normalizeSeed([
            'license' => 'example.tld/terms',
            'creator' => [['name' => 'ExampleCreator', 'type' => 'Organization']],
            'creditText' => '  ',
            'digitalSourceType' => 'trainedAlgorithmicMedia',
            'unknownKey' => 'ignored',
        ]);

        self::assertSame([
            'license' => 'https://example.tld/terms',
            'creator' => [['name' => 'ExampleCreator', 'type' => 'Organization']],
        ], $seed);
    }

    /** The short form an app config or a compact input can give. */
    public function testASeededCreatorMayBeAPlainName(): void
    {
        self::assertSame(
            ['creator' => [['name' => 'ExampleCreator', 'type' => 'Person']]],
            MediaLicense::normalizeSeed(['creator' => 'ExampleCreator']),
        );
    }

    /**
     * A creator carries its own type, so a photographer and the agency that
     * commissioned the shot can be credited on the same image.
     */
    public function testCreatorsKeepTheirOwnType(): void
    {
        self::assertSame([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'ExampleCreator', 'type' => 'Organization'],
        ], MediaLicense::normalizeCreators([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'ExampleCreator', 'type' => 'Organization'],
        ]));
    }

    public function testTheCompactTextFormRoundTrips(): void
    {
        $creators = MediaLicense::normalizeCreators('Robin (Person), ExampleCreator (Organization)');

        self::assertSame([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'ExampleCreator', 'type' => 'Organization'],
        ], $creators);
        self::assertSame('Robin (Person), ExampleCreator (Organization)', MediaLicense::formatCreators($creators));
    }

    /** Bare names are what a file gives; Person is the fallback, never a rejection. */
    public function testBareNamesBecomePeopleAndDeduplicate(): void
    {
        self::assertSame([
            ['name' => 'Dominique VIVARES', 'type' => 'Person'],
            ['name' => 'Jean Dupont', 'type' => 'Person'],
        ], MediaLicense::normalizeCreators(' Dominique VIVARES , Jean Dupont , , Jean Dupont '));
    }

    public function testAnUnknownTypeFallsBackToPerson(): void
    {
        self::assertSame(
            [['name' => 'ExampleCreator', 'type' => 'Person']],
            MediaLicense::normalizeCreators([['name' => 'ExampleCreator', 'type' => 'Robot']]),
        );
    }

    /** A parenthetical is only a type when it names one — otherwise it is part of the name. */
    public function testAParentheticalThatIsNotATypeStaysInTheName(): void
    {
        self::assertSame(
            [['name' => 'Jean (Jean-Pierre) Dupont', 'type' => 'Person']],
            MediaLicense::normalizeCreators('Jean (Jean-Pierre) Dupont'),
        );
    }

    public function testNameListSplitsAndDeduplicates(): void
    {
        self::assertSame(
            ['Dominique VIVARES', 'Jean Dupont'],
            MediaLicense::normalizeNameList(' Dominique VIVARES , Jean Dupont , , Jean Dupont '),
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    private static function mediaWith(array $properties): Media
    {
        $media = new Media();

        foreach ($properties as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        return $media;
    }

    public function testAMediaClaimingNothingHasNoCreditLine(): void
    {
        self::assertSame('', MediaLicense::creditLine(self::mediaWith([])));
    }

    public function testACreditTextBecomesASignedLine(): void
    {
        self::assertSame('© Wilfrid Valette', MediaLicense::creditLine(
            self::mediaWith([MediaLicense::CREDIT_TEXT => 'Wilfrid Valette']),
        ));
    }

    /**
     * The one case that makes the whole line worth rendering: BY-SA is not satisfied
     * by naming the author alone, the deed has to be named with them.
     */
    public function testACreativeCommonsDeedIsNamedBesideTheAuthor(): void
    {
        self::assertSame('© Zde / Wikimedia (CC BY-SA 4.0)', MediaLicense::creditLine(
            self::mediaWith([
                MediaLicense::CREDIT_TEXT => 'Zde / Wikimedia',
                MediaLicense::LICENSE => 'https://creativecommons.org/licenses/by-sa/4.0/',
            ]),
        ));
    }

    /** A stock platform's terms page has no name a visitor could act on. */
    public function testAnUnlabellableLicenceAddsNothingToTheLine(): void
    {
        self::assertSame('© Pixabay', MediaLicense::creditLine(
            self::mediaWith([
                MediaLicense::CREDIT_TEXT => 'Pixabay',
                MediaLicense::LICENSE => 'https://pixabay.com/service/license/',
            ]),
        ));
    }

    /** The rights holder's own wording is not ours to re-punctuate. */
    public function testACopyrightNoticeIsUsedVerbatimAndOutranksTheCreditText(): void
    {
        self::assertSame('Copyright 1998 Grand Angle, all rights reserved', MediaLicense::creditLine(
            self::mediaWith([
                MediaLicense::COPYRIGHT_NOTICE => 'Copyright 1998 Grand Angle, all rights reserved',
                MediaLicense::CREDIT_TEXT => 'Grand Angle',
            ]),
        ));
    }

    /** Libraries imported from an older convention carry the symbol inside the credit. */
    public function testASymbolAlreadyInTheCreditIsNotDoubled(): void
    {
        self::assertSame('© Thomas Praire', MediaLicense::creditLine(
            self::mediaWith([MediaLicense::CREDIT_TEXT => '© Thomas Praire']),
        ));
    }

    public function testCreatorsAreUsedWhenNoCreditTextWasWritten(): void
    {
        self::assertSame('© Thomas Praire, Grand Angle', MediaLicense::creditLine(
            self::mediaWith([MediaLicense::CREATOR => [
                ['name' => 'Thomas Praire', 'type' => MediaLicense::CREATOR_TYPE_PERSON],
                ['name' => 'Grand Angle', 'type' => MediaLicense::CREATOR_TYPE_ORGANIZATION],
            ]]),
        ));
    }

    /**
     * A deed with nobody to attribute still states what a visitor may do with the
     * file, so it is worth a line — but it must not invent a "©" over an empty name.
     */
    public function testADeedWithoutAnAuthorStandsAlone(): void
    {
        self::assertSame('CC0 1.0', MediaLicense::creditLine(
            self::mediaWith([MediaLicense::LICENSE => 'https://creativecommons.org/publicdomain/zero/1.0/']),
        ));
    }

    #[DataProvider('licenseLabelProvider')]
    public function testLicenceLabelling(string $url, string $expected): void
    {
        self::assertSame($expected, MediaLicense::licenseLabel($url));
    }

    /** @return iterable<string, array{string, string}> */
    public static function licenseLabelProvider(): iterable
    {
        yield 'attribution' => ['https://creativecommons.org/licenses/by/4.0/', 'CC BY 4.0'];
        yield 'share alike' => ['https://creativecommons.org/licenses/by-sa/4.0/', 'CC BY-SA 4.0'];
        yield 'three clauses' => ['https://creativecommons.org/licenses/by-nc-nd/3.0/', 'CC BY-NC-ND 3.0'];
        yield 'a ported deed keeps the version, not the jurisdiction' => ['https://creativecommons.org/licenses/by-sa/2.0/fr/', 'CC BY-SA 2.0'];
        yield 'http' => ['http://creativecommons.org/licenses/by/2.5/', 'CC BY 2.5'];
        yield 'www' => ['https://www.creativecommons.org/licenses/by/4.0/', 'CC BY 4.0'];
        yield 'public domain dedication' => ['https://creativecommons.org/publicdomain/zero/1.0/', 'CC0 1.0'];
        yield 'public domain mark' => ['https://creativecommons.org/publicdomain/mark/1.0/', 'Public Domain Mark 1.0'];
        yield 'a stock platform is not labelled' => ['https://pixabay.com/service/license/', ''];
        yield 'a lookalike host is not creative commons' => ['https://creativecommons.org.example.test/licenses/by/4.0/', ''];
        yield 'empty' => ['', ''];
    }
}
