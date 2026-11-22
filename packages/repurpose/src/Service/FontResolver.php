<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\FontPairing;

/**
 * Resolves a font pairing — a registry key or a custom {@see FontPairing} — to
 * the heading and body TTF files on disk.
 *
 * FreeType (the measurement primitive) needs a real TTF/OTF file, so each family
 * is looked up at the weight of its role (headings bold, body regular) — first
 * in the app font dir (where `pw:repurpose:fonts` and the studio install them,
 * `repurpose.font_dir`), then in the bundled font dir. Anything not installed
 * falls back to the bundled Apache-2.0 Roboto, which is always present.
 */
final readonly class FontResolver
{
    public const int HEADING_WEIGHT = 700;

    public const int BODY_WEIGHT = 400;

    private const string HEADING_FALLBACK = 'roboto-bold.ttf';

    private const string BODY_FALLBACK = 'roboto-regular.ttf';

    private string $bundleDir;

    public function __construct(
        private FontPairingRegistry $pairings,
        private ?string $fontDir = null,
        ?string $bundleDir = null,
    ) {
        $this->bundleDir = $bundleDir ?? __DIR__.'/../Resources/font';
    }

    /**
     * Where downloaded TTFs are written (the bundle dir when no app dir is
     * configured — only the case in bare unit tests).
     */
    public function installDir(): string
    {
        return $this->fontDir ?? $this->bundleDir;
    }

    public function headingFile(string|FontPairing|null $pairing): string
    {
        return $this->resolve($this->familyOf($pairing, 'heading'), self::HEADING_WEIGHT, self::HEADING_FALLBACK);
    }

    public function bodyFile(string|FontPairing|null $pairing): string
    {
        return $this->resolve($this->familyOf($pairing, 'body'), self::BODY_WEIGHT, self::BODY_FALLBACK);
    }

    /**
     * The CSS family name to declare for the heading (resolved family, or Roboto).
     */
    public function headingFamily(string|FontPairing|null $pairing): string
    {
        $family = $this->familyOf($pairing, 'heading');

        return null !== $family && $this->hasFace($family, self::HEADING_WEIGHT) ? $family : 'Roboto';
    }

    /**
     * The faces a pairing draws with, each family at the weight of its role.
     * Empty for no pairing or an unknown registry key.
     *
     * @return array<'heading'|'body', array{family: string, weight: int}>
     */
    public function faces(string|FontPairing|null $pairing): array
    {
        $heading = $this->familyOf($pairing, 'heading');
        $body = $this->familyOf($pairing, 'body');
        if (null === $heading || null === $body) {
            return [];
        }

        return [
            'heading' => ['family' => $heading, 'weight' => self::HEADING_WEIGHT],
            'body' => ['family' => $body, 'weight' => self::BODY_WEIGHT],
        ];
    }

    /**
     * True when the pairing's own font files are installed (not falling back).
     */
    public function isInstalled(string|FontPairing $pairing): bool
    {
        $faces = $this->faces($pairing);

        return [] !== $faces
            && array_all($faces, fn (array $face): bool => $this->hasFace($face['family'], $face['weight']));
    }

    /**
     * The on-disk filename of a family at a weight. A registry family keeps its
     * historical suffix-free name at the weight of its registry role (bold for a
     * heading family); any other weight gets a `-<weight>` suffix, so a bold file
     * never serves body text and a custom pairing can reuse a registry family.
     */
    public function fileNameFor(string $family, int $weight): string
    {
        $registryWeight = FontPairingRegistry::isHeadingFamily($family) ? self::HEADING_WEIGHT : self::BODY_WEIGHT;
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $family));

        return $weight === $registryWeight ? $slug.'.ttf' : $slug.'-'.$weight.'.ttf';
    }

    /**
     * True when the family's TTF at that weight is present (app dir or bundled).
     */
    public function hasFace(string $family, int $weight): bool
    {
        return null !== $this->fileFor($family, $weight);
    }

    /**
     * @param 'heading'|'body' $role
     */
    private function familyOf(string|FontPairing|null $pairing, string $role): ?string
    {
        if ($pairing instanceof FontPairing) {
            return 'heading' === $role ? $pairing->heading : $pairing->body;
        }

        $entry = null === $pairing ? null : $this->pairings->get($pairing);
        if (null === $entry) {
            return null;
        }

        return 'heading' === $role ? $entry['heading'] : $entry['body'];
    }

    private function resolve(?string $family, int $weight, string $fallback): string
    {
        $file = null === $family ? null : $this->fileFor($family, $weight);

        return $file ?? $this->bundleDir.'/'.$fallback;
    }

    private function fileFor(string $family, int $weight): ?string
    {
        $name = $this->fileNameFor($family, $weight);
        foreach ([$this->fontDir, $this->bundleDir] as $dir) {
            if (null !== $dir && is_file($dir.'/'.$name)) {
                return $dir.'/'.$name;
            }
        }

        return null;
    }
}
