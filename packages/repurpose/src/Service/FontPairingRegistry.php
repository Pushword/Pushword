<?php

namespace Pushword\Repurpose\Service;

/**
 * Heading + body font pairings a carousel can use.
 *
 * The package ships **metadata for every pairing** (so the schema, the API and an
 * agent always know what exists) but bundles only a small default set of TTFs; the
 * rest are fetched on demand with `pw:repurpose:fonts <pairing>`. Every family
 * here is on Google Fonts (OFL 1.1 or Apache 2.0), so embedding and
 * redistribution are permitted.
 *
 * `heading`/`body` are the CSS family names (also the Google Fonts family names).
 * `bundled: true` marks a pairing whose TTFs ship with the package — kept true by
 * {@see \Pushword\Repurpose\Tests\Service\RegistryConsistencyTest}, which checks
 * the files are really on disk. Whether a pairing is usable *right now* (bundled
 * or installed) is `FontResolver::isInstalled()`, exposed per pairing at
 * `GET /api/repurpose/networks`.
 */
final class FontPairingRegistry
{
    /**
     * @var array<string, array{heading: string, body: string, bundled?: bool, cjk?: bool}>
     */
    public const array PAIRINGS = [
        'dm-serif-display-dm-sans' => ['heading' => 'DM Serif Display', 'body' => 'DM Sans', 'bundled' => true],
        'playfair-chivo' => ['heading' => 'Playfair Display', 'body' => 'Chivo', 'bundled' => true],
        'montserrat-work-sans' => ['heading' => 'Montserrat', 'body' => 'Work Sans', 'bundled' => true],
        'poppins-inter' => ['heading' => 'Poppins', 'body' => 'Inter', 'bundled' => true],
        'anton-roboto' => ['heading' => 'Anton', 'body' => 'Roboto', 'bundled' => true],
        'lora-ubuntu' => ['heading' => 'Lora', 'body' => 'Ubuntu', 'bundled' => true],
        'rozha-one-questrial' => ['heading' => 'Rozha One', 'body' => 'Questrial'],
        'arvo-roboto' => ['heading' => 'Arvo', 'body' => 'Roboto'],
        'merriweather-lora' => ['heading' => 'Merriweather', 'body' => 'Lora'],
        'nixie-one-prompt' => ['heading' => 'Nixie One', 'body' => 'Prompt'],
        'libre-baskerville-space-grotesk' => ['heading' => 'Libre Baskerville', 'body' => 'Space Grotesk'],
        'abril-fatface-poppins' => ['heading' => 'Abril Fatface', 'body' => 'Poppins'],
        'ultra-pt-serif' => ['heading' => 'Ultra', 'body' => 'PT Serif'],
        'alfa-slab-one-gentium-plus' => ['heading' => 'Alfa Slab One', 'body' => 'Gentium Plus'],
        'archivo-black-archivo' => ['heading' => 'Archivo Black', 'body' => 'Archivo'],
        'bangers-oswald' => ['heading' => 'Bangers', 'body' => 'Oswald'],
        'bebas-neue-lato' => ['heading' => 'Bebas Neue', 'body' => 'Lato'],
        'gravitas-one-poppins' => ['heading' => 'Gravitas One', 'body' => 'Poppins'],
        'bungee-varela' => ['heading' => 'Bungee', 'body' => 'Varela'],
        'black-han-sans-inter' => ['heading' => 'Black Han Sans', 'body' => 'Inter', 'cjk' => true],
        'big-shoulders-display-inter' => ['heading' => 'Big Shoulders Display', 'body' => 'Inter'],
        'fjalla-one-cantarell' => ['heading' => 'Fjalla One', 'body' => 'Cantarell'],
        'syne-inter' => ['heading' => 'Syne', 'body' => 'Inter'],
        'rubik-roboto-mono' => ['heading' => 'Rubik', 'body' => 'Roboto Mono'],
        'league-spartan-work-sans' => ['heading' => 'League Spartan', 'body' => 'Work Sans'],
        'bricolage-grotesque-lato' => ['heading' => 'Bricolage Grotesque', 'body' => 'Lato'],
        'ibm-plex-sans' => ['heading' => 'IBM Plex Sans', 'body' => 'IBM Plex Sans Condensed'],
        'unbounded-pontano-sans' => ['heading' => 'Unbounded', 'body' => 'Pontano Sans'],
        'poetsen-one-mulish' => ['heading' => 'Poetsen One', 'body' => 'Mulish'],
        'shrikhand-nunito' => ['heading' => 'Shrikhand', 'body' => 'Nunito'],
        'fugaz-one-lato' => ['heading' => 'Fugaz One', 'body' => 'Lato'],
        'sansita-black-overpass' => ['heading' => 'Sansita', 'body' => 'Overpass'],
        'unica-one-crimson-text' => ['heading' => 'Unica One', 'body' => 'Crimson Text'],
        'teko-ubuntu' => ['heading' => 'Teko', 'body' => 'Ubuntu'],
        'jetbrains-mono-rubik' => ['heading' => 'JetBrains Mono', 'body' => 'Rubik'],
        'chakra-petch-rubik' => ['heading' => 'Chakra Petch', 'body' => 'Rubik'],
        'rajdhani-inter' => ['heading' => 'Rajdhani', 'body' => 'Inter'],
        'bungee-hairline-chakra-petch' => ['heading' => 'Bungee Hairline', 'body' => 'Chakra Petch'],
        'tektur-jetbrains-mono' => ['heading' => 'Tektur', 'body' => 'JetBrains Mono'],
        'yellowtail-lato' => ['heading' => 'Yellowtail', 'body' => 'Lato'],
        'courgette-libre-baskerville' => ['heading' => 'Courgette', 'body' => 'Libre Baskerville'],
        'permanent-marker-abeezee' => ['heading' => 'Permanent Marker', 'body' => 'ABeeZee'],
        'architects-daughter-abel' => ['heading' => 'Architects Daughter', 'body' => 'Abel'],
        'dancing-script-jost' => ['heading' => 'Dancing Script', 'body' => 'Jost'],
        'kalam-rosario' => ['heading' => 'Kalam', 'body' => 'Rosario'],
        'sniglet-varela-round' => ['heading' => 'Sniglet', 'body' => 'Varela Round'],
        'sigmar-one-work-sans' => ['heading' => 'Sigmar One', 'body' => 'Work Sans'],
        'paytone-one-open-sans' => ['heading' => 'Paytone One', 'body' => 'Open Sans'],
        'chewy-mulish' => ['heading' => 'Chewy', 'body' => 'Mulish'],
        'fredoka-karla' => ['heading' => 'Fredoka', 'body' => 'Karla'],
        'varela-round-ibm-plex-sans' => ['heading' => 'Varela Round', 'body' => 'IBM Plex Sans'],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::PAIRINGS);
    }

    /**
     * @return array{heading: string, body: string, bundled?: bool, cjk?: bool}|null
     */
    public function get(string $key): ?array
    {
        return self::PAIRINGS[$key] ?? null;
    }

    public function isBundled(string $key): bool
    {
        return self::PAIRINGS[$key]['bundled'] ?? false;
    }

    /**
     * True when the family headlines at least one pairing. One TTF serves a
     * family everywhere, so heading families are fetched at weight 700 (headings
     * must read bold) and body-only families at 400.
     */
    public static function isHeadingFamily(string $family): bool
    {
        return array_any(self::PAIRINGS, static fn (array $pairing): bool => $pairing['heading'] === $family);
    }

    /**
     * @return array<string, array{heading: string, body: string, bundled?: bool, cjk?: bool}>
     */
    public function all(): array
    {
        return self::PAIRINGS;
    }
}
