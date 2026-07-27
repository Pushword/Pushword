<?php

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
            'creator' => [['name' => 'Altimood', 'type' => 'Organization']],
            'creditText' => '  ',
            'digitalSourceType' => 'trainedAlgorithmicMedia',
            'unknownKey' => 'ignored',
        ]);

        self::assertSame([
            'license' => 'https://example.tld/terms',
            'creator' => [['name' => 'Altimood', 'type' => 'Organization']],
        ], $seed);
    }

    /** The short form an app config or a compact input can give. */
    public function testASeededCreatorMayBeAPlainName(): void
    {
        self::assertSame(
            ['creator' => [['name' => 'Altimood', 'type' => 'Person']]],
            MediaLicense::normalizeSeed(['creator' => 'Altimood']),
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
            ['name' => 'Altimood', 'type' => 'Organization'],
        ], MediaLicense::normalizeCreators([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'Altimood', 'type' => 'Organization'],
        ]));
    }

    public function testTheCompactTextFormRoundTrips(): void
    {
        $creators = MediaLicense::normalizeCreators('Robin (Person), Altimood (Organization)');

        self::assertSame([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'Altimood', 'type' => 'Organization'],
        ], $creators);
        self::assertSame('Robin (Person), Altimood (Organization)', MediaLicense::formatCreators($creators));
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
            [['name' => 'Altimood', 'type' => 'Person']],
            MediaLicense::normalizeCreators([['name' => 'Altimood', 'type' => 'Robot']]),
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
}
