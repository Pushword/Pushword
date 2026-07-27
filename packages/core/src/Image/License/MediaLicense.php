<?php

namespace Pushword\Core\Image\License;

use Pushword\Core\Entity\Media;

use function Safe\preg_match;

/**
 * Keys, states and vocabularies for the image license metadata Google reads to
 * award the "Licensable" badge.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata
 *
 * The six property keys live in Media::customProperties; licenseState is a real
 * column so the admin can filter and sort on it.
 */
final class MediaLicense
{
    public const string LICENSE = 'license';

    public const string ACQUIRE_LICENSE_PAGE = 'acquireLicensePage';

    public const string CREDIT_TEXT = 'creditText';

    /**
     * A list of {name, type} — the shape schema.org emits, one node per creator.
     * The type belongs to the name, not to the media: a photo credited to a
     * photographer and to the agency that commissioned it holds both kinds at once.
     */
    public const string CREATOR = 'creator';

    public const string COPYRIGHT_NOTICE = 'copyrightNotice';

    public const string DIGITAL_SOURCE_TYPE = 'digitalSourceType';

    /** @var string[] every managed custom property key */
    public const array KEYS = [
        self::LICENSE,
        self::ACQUIRE_LICENSE_PAGE,
        self::CREDIT_TEXT,
        self::CREATOR,
        self::COPYRIGHT_NOTICE,
        self::DIGITAL_SOURCE_TYPE,
    ];

    /** @var string[] keys the app config may seed (digitalSourceType describes the file, never the site) */
    public const array SEEDABLE_KEYS = [
        self::LICENSE,
        self::ACQUIRE_LICENSE_PAGE,
        self::CREDIT_TEXT,
        self::CREATOR,
        self::COPYRIGHT_NOTICE,
    ];

    /**
     * Keys whose presence in a file is somebody's rights claim. digitalSourceType is
     * provenance rather than a claim, so it does not gate.
     */
    public const array RIGHTS_KEYS = [
        self::LICENSE,
        self::ACQUIRE_LICENSE_PAGE,
        self::CREDIT_TEXT,
        self::CREATOR,
        self::COPYRIGHT_NOTICE,
    ];

    /**
     * Google needs contentUrl plus at least one of these. acquireLicensePage is
     * deliberately absent from the list: on its own it does not make an image eligible.
     */
    public const array GOOGLE_MINIMUM_KEYS = [
        self::CREATOR,
        self::CREDIT_TEXT,
        self::COPYRIGHT_NOTICE,
        self::LICENSE,
    ];

    /** @var string[] keys holding a URL, normalized on import and validated in the admin */
    public const array URL_KEYS = [self::LICENSE, self::ACQUIRE_LICENSE_PAGE];

    public const string STATE_NONE = '';

    public const string STATE_SEEDED = 'seeded';

    public const string STATE_OVERRIDDEN = 'overridden';

    public const string STATE_THIRD_PARTY = 'thirdParty';

    /** @var string[] */
    public const array STATES = [self::STATE_SEEDED, self::STATE_OVERRIDDEN, self::STATE_THIRD_PARTY];

    public const string CREATOR_TYPE_PERSON = 'Person';

    public const string CREATOR_TYPE_ORGANIZATION = 'Organization';

    /** @var string[] */
    public const array CREATOR_TYPES = [self::CREATOR_TYPE_PERSON, self::CREATOR_TYPE_ORGANIZATION];

    public const string DIGITAL_SOURCE_TYPE_PREFIX = 'http://cv.iptc.org/newscodes/digitalsourcetype/';

    public const string TRAINED_ALGORITHMIC_MEDIA = 'trainedAlgorithmicMedia';

    /** @var string[] IPTC NewsCodes for digitalsourcetype, in their canonical casing */
    public const array DIGITAL_SOURCE_TYPES = [
        'digitalCapture',
        'negativeFilm',
        'positiveFilm',
        'print',
        'minorHumanEdits',
        'compositeCapture',
        'algorithmicallyEnhanced',
        'dataDrivenMedia',
        'digitalArt',
        'virtualRecording',
        'composite',
        'compositeSynthetic',
        'compositeWithTrainedAlgorithmicMedia',
        self::TRAINED_ALGORITHMIC_MEDIA,
        'algorithmicMedia',
    ];

    /**
     * Credit lines generative tools write as a provenance note, next to
     * Iptc4xmpExt:DigitalSourceType. ChatGPT stamps "AI Generated" into IIM 2#110,
     * Gemini "Made with Google AI".
     *
     * Matched exactly, trimmed and case-insensitively: a substring match for "AI"
     * would swallow a legitimate agency name such as "AI Generated Studio Ltd".
     * Extend this list as other generators show up.
     *
     * @var string[] lowercased
     */
    public const array GENERATOR_CREDITS = ['ai generated', 'made with google ai'];

    /**
     * Read the license properties carried by a media, dropping empty ones.
     *
     * @return array<string, mixed>
     */
    public static function extract(Media $media): array
    {
        $values = [];

        foreach (self::KEYS as $key) {
            $value = $media->getCustomProperty($key);

            if (self::CREATOR === $key) {
                $creators = self::normalizeCreators($value);
                if ([] !== $creators) {
                    $values[$key] = $creators;
                }

                continue;
            }

            if (\is_scalar($value) && '' !== trim((string) $value)) {
                $values[$key] = trim((string) $value);
            }
        }

        return $values;
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    public static function creators(Media $media): array
    {
        return self::normalizeCreators($media->getCustomProperty(self::CREATOR));
    }

    /**
     * True when the values claim rights for somebody — the gate deciding whether a
     * freshly uploaded file may receive the site's own licensing.
     *
     * @param array<string, mixed> $values
     */
    public static function hasRightsValue(array $values): bool
    {
        return array_any(self::RIGHTS_KEYS, static fn (string $key): bool => isset($values[$key]) && [] !== $values[$key] && '' !== $values[$key]);
    }

    /**
     * Keep only what an app may seed, in the canonical shape.
     *
     * @param array<array-key, mixed> $raw
     *
     * @return array<string, string|list<array{name: string, type: string}>>
     */
    public static function normalizeSeed(array $raw): array
    {
        $seed = [];

        foreach (self::SEEDABLE_KEYS as $key) {
            $value = $raw[$key] ?? null;

            if (self::CREATOR === $key) {
                $creators = self::normalizeCreators($value);
                if ([] !== $creators) {
                    $seed[$key] = $creators;
                }

                continue;
            }

            if (! \is_scalar($value)) {
                continue;
            }

            if ('' === trim((string) $value)) {
                continue;
            }

            $value = trim((string) $value);

            if (\in_array($key, self::URL_KEYS, true)) {
                $value = self::normalizeUrl($value);
                if ('' === $value) {
                    continue;
                }
            }

            $seed[$key] = $value;
        }

        return $seed;
    }

    /**
     * Embedded URLs commonly arrive as a bare hostname ("www.example.com"), which
     * both the admin UrlField and schema.org reject. Anything that cannot be a host
     * — prose, whitespace, no dot — is dropped rather than turned into a broken URL.
     */
    public static function normalizeUrl(string $value): string
    {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            $value = substr($value, 2);
        }

        if (1 !== preg_match('#^[^\s/]+\.[^\s/]+#', $value)) {
            return '';
        }

        return 'https://'.$value;
    }

    /**
     * Map a DigitalSourceType to its canonical IPTC NewsCode URI. Files disagree with
     * each other: the same corpus carries both the bare token "TrainedAlgorithmicMedia"
     * and the full URI.
     */
    public static function normalizeDigitalSourceType(string $value): string
    {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        $separator = strrpos($value, '/');
        $token = false === $separator ? $value : substr($value, $separator + 1);

        if ('' === $token) {
            return '';
        }

        foreach (self::DIGITAL_SOURCE_TYPES as $known) {
            if (0 === strcasecmp($known, $token)) {
                return self::DIGITAL_SOURCE_TYPE_PREFIX.$known;
            }
        }

        return self::DIGITAL_SOURCE_TYPE_PREFIX.lcfirst($token);
    }

    public static function isGeneratorCredit(string $creditText): bool
    {
        return \in_array(strtolower(trim($creditText)), self::GENERATOR_CREDITS, true);
    }

    /**
     * Every shape a creator list arrives in, reduced to the stored one — each name
     * carrying its own type, because that is what schema.org emits per node.
     *
     * Accepts what each source can actually hand over:
     *   'Robin, Altimood (Organization)'              a single text input (inline row, config)
     *   ['Robin', 'Altimood']                         bare names, as read from a file
     *   [['name' => 'Robin', 'type' => 'Person'], …]  the stored shape
     *
     * @return list<array{name: string, type: string}>
     */
    public static function normalizeCreators(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (! \is_array($value)) {
            return [];
        }

        $creators = [];
        foreach ($value as $entry) {
            $creator = self::normalizeCreator($entry);
            if (null === $creator) {
                continue;
            }

            if (array_any($creators, static fn (array $known): bool => $known['name'] === $creator['name'])) {
                continue;
            }

            $creators[] = $creator;
        }

        return $creators;
    }

    /**
     * The compact form a single text input can carry, and what the multi-upload row
     * shows back. Always explicit, so the syntax is visible in the field itself.
     *
     * @param list<array{name: string, type: string}> $creators
     */
    public static function formatCreators(array $creators): string
    {
        return implode(', ', array_map(
            static fn (array $creator): string => $creator['name'].' ('.$creator['type'].')',
            $creators,
        ));
    }

    /**
     * @return array{name: string, type: string}|null
     */
    private static function normalizeCreator(mixed $entry): ?array
    {
        if (\is_array($entry)) {
            $name = self::scalarOrEmpty($entry['name'] ?? null);
            $type = self::scalarOrEmpty($entry['type'] ?? null);

            return '' === $name ? null : ['name' => $name, 'type' => self::creatorType($type)];
        }

        if (! \is_scalar($entry)) {
            return null;
        }

        $name = trim((string) $entry);
        $type = '';

        // "Altimood (Organization)". Only stripped when the trailing parenthetical
        // really names a type — otherwise "Jean (Jean-Pierre)" would lose half its name.
        $open = str_ends_with($name, ')') ? strrpos($name, '(') : false;
        if (false !== $open && self::isCreatorType($candidate = substr($name, $open + 1, -1))) {
            $name = trim(substr($name, 0, $open));
            $type = $candidate;
        }

        return '' === $name ? null : ['name' => $name, 'type' => self::creatorType($type)];
    }

    private static function scalarOrEmpty(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    private static function isCreatorType(string $type): bool
    {
        return array_any(self::CREATOR_TYPES, static fn (string $known): bool => 0 === strcasecmp($known, trim($type)));
    }

    // Person is the fallback: a by-line is one often enough, and guessing the schema.org
    // type wrong is cosmetic, not a false claim about who owns the image.
    private static function creatorType(string $type): string
    {
        foreach (self::CREATOR_TYPES as $known) {
            if (0 === strcasecmp($known, trim($type))) {
                return $known;
            }
        }

        return self::CREATOR_TYPE_PERSON;
    }

    /**
     * Names as a file gives them: dc:creator is an rdf:Seq of bare strings, IPTC
     * By-line and EXIF Artist are plain text. No source carries a type.
     *
     * @return string[]
     */
    public static function normalizeNameList(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (! \is_array($value)) {
            return [];
        }

        $names = [];
        foreach ($value as $name) {
            if (! \is_scalar($name)) {
                continue;
            }

            $name = trim((string) $name);
            if ('' !== $name && ! \in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
