<?php

namespace Pushword\Repurpose\Service;

/**
 * Resolves a font pairing to the heading and body TTF files on disk.
 *
 * FreeType (the measurement primitive) needs a real TTF/OTF file, so a pairing's
 * families are looked up as `<family-slug>.ttf` — first in the app font dir
 * (where `pw:repurpose:fonts <pairing>` installs them, `repurpose.font_dir`),
 * then in the bundled font dir. Anything not installed falls back to the bundled
 * Apache-2.0 Roboto, which is always present.
 */
final readonly class FontResolver
{
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
     * Where `pw:repurpose:fonts` writes downloaded TTFs (the bundle dir when no
     * app dir is configured — only the case in bare unit tests).
     */
    public function installDir(): string
    {
        return $this->fontDir ?? $this->bundleDir;
    }

    public function headingFile(?string $pairingKey): string
    {
        return $this->resolve($this->familyOf($pairingKey, 'heading'), self::HEADING_FALLBACK);
    }

    public function bodyFile(?string $pairingKey): string
    {
        return $this->resolve($this->familyOf($pairingKey, 'body'), self::BODY_FALLBACK);
    }

    /**
     * The CSS family name to declare for the heading (resolved family, or Roboto).
     */
    public function headingFamily(?string $pairingKey): string
    {
        return $this->cssFamily($this->familyOf($pairingKey, 'heading'));
    }

    public function bodyFamily(?string $pairingKey): string
    {
        return $this->cssFamily($this->familyOf($pairingKey, 'body'));
    }

    /**
     * True when the pairing's own font files are installed (not falling back).
     */
    public function isInstalled(string $pairingKey): bool
    {
        $heading = $this->familyOf($pairingKey, 'heading');
        $body = $this->familyOf($pairingKey, 'body');

        return null !== $heading && null !== $body
            && $this->hasFamily($heading) && $this->hasFamily($body);
    }

    /**
     * The on-disk filename a family resolves to, in any font dir.
     */
    public function fileNameFor(string $family): string
    {
        return $this->slug($family).'.ttf';
    }

    /**
     * True when the family's TTF is present (app dir or bundled).
     */
    public function hasFamily(string $family): bool
    {
        return null !== $this->fileFor($family);
    }

    /**
     * @param 'heading'|'body' $role
     */
    private function familyOf(?string $pairingKey, string $role): ?string
    {
        if (null === $pairingKey) {
            return null;
        }

        $pairing = $this->pairings->get($pairingKey);
        if (null === $pairing) {
            return null;
        }

        return 'heading' === $role ? $pairing['heading'] : $pairing['body'];
    }

    private function resolve(?string $family, string $fallback): string
    {
        if (null !== $family) {
            $file = $this->fileFor($family);
            if (null !== $file) {
                return $file;
            }
        }

        return $this->bundleDir.'/'.$fallback;
    }

    private function cssFamily(?string $family): string
    {
        if (null !== $family && null !== $this->fileFor($family)) {
            return $family;
        }

        return 'Roboto';
    }

    private function fileFor(string $family): ?string
    {
        $name = $this->fileNameFor($family);
        foreach ([$this->fontDir, $this->bundleDir] as $dir) {
            if (null !== $dir && is_file($dir.'/'.$name)) {
                return $dir.'/'.$name;
            }
        }

        return null;
    }

    private function slug(string $family): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $family));
    }
}
