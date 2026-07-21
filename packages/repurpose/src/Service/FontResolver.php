<?php

namespace Pushword\Repurpose\Service;

/**
 * Resolves a font pairing to the heading and body TTF files on disk.
 *
 * FreeType (the measurement primitive) needs a real TTF/OTF file, so a pairing's
 * families are looked up as `<family-slug>.ttf` in the bundled font dir. Anything
 * not installed falls back to the bundled Apache-2.0 Roboto, which is always
 * present — `pw:repurpose:fonts install <pairing>` fetches the prettier statics.
 */
final readonly class FontResolver
{
    private const string HEADING_FALLBACK = 'roboto-bold.ttf';

    private const string BODY_FALLBACK = 'roboto-regular.ttf';

    private string $fontDir;

    public function __construct(
        private FontPairingRegistry $pairings,
        ?string $fontDir = null,
    ) {
        $this->fontDir = $fontDir ?? __DIR__.'/../Resources/font';
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
            && is_file($this->fileFor($heading)) && is_file($this->fileFor($body));
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
            if (is_file($file)) {
                return $file;
            }
        }

        return $this->fontDir.'/'.$fallback;
    }

    private function cssFamily(?string $family): string
    {
        if (null !== $family && is_file($this->fileFor($family))) {
            return $family;
        }

        return 'Roboto';
    }

    private function fileFor(string $family): string
    {
        return $this->fontDir.'/'.$this->slug($family).'.ttf';
    }

    private function slug(string $family): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $family));
    }
}
