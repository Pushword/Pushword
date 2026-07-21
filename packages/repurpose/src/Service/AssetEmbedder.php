<?php

namespace Pushword\Repurpose\Service;

/**
 * Turns fonts and images into `data:` URIs so a slide SVG is one portable file:
 * it renders in any browser, rasterises to canvas untainted (no CORS), and never
 * falls back to a missing font. An SVG loaded as `<img>` for canvas export is a
 * closed context that cannot fetch external resources, so embedding is mandatory,
 * not an optimisation.
 */
final class AssetEmbedder
{
    /**
     * A base64 `data:` URI for a TTF font file.
     */
    public function fontDataUri(string $ttfPath): string
    {
        return 'data:font/ttf;base64,'.base64_encode((string) @file_get_contents($ttfPath));
    }

    /**
     * A `@font-face` rule embedding the file under the given CSS family name.
     */
    public function fontFace(string $family, string $ttfPath): string
    {
        return \sprintf(
            "@font-face{font-family:'%s';src:url('%s') format('truetype');}",
            addslashes($family),
            $this->fontDataUri($ttfPath),
        );
    }

    /**
     * A base64 `data:` URI for an image file (webp/jpeg/png), mime detected from
     * the extension. Returns null when the file is missing so the renderer can
     * degrade to a placeholder rather than emit a broken slide.
     */
    public function imageDataUri(string $imagePath): ?string
    {
        if (! is_file($imagePath)) {
            return null;
        }

        $bytes = @file_get_contents($imagePath);
        if (false === $bytes || '' === $bytes) {
            return null;
        }

        return 'data:'.$this->mime($imagePath).';base64,'.base64_encode($bytes);
    }

    private function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, \PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }
}
