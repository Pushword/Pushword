<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\AssetEmbedder;

/**
 * AssetEmbedder turns fonts and images into `data:` URIs so a slide SVG is one
 * portable, untainted file. The load-bearing behaviours: bytes survive the
 * round-trip, the `@font-face` names the family, and a missing image degrades to
 * null (so the renderer can draw a placeholder) rather than emitting a broken URI.
 */
#[Group('integration')]
final class AssetEmbedderTest extends TestCase
{
    private const string FONT = __DIR__.'/../../src/Resources/font/roboto-bold.ttf';

    public function testFontDataUriEmbedsTheFileBytes(): void
    {
        $uri = new AssetEmbedder()->fontDataUri(self::FONT);

        self::assertStringStartsWith('data:font/ttf;base64,', $uri);
        $decoded = base64_decode(substr($uri, \strlen('data:font/ttf;base64,')), true);
        self::assertSame(file_get_contents(self::FONT), $decoded);
    }

    public function testFontFaceCarriesFamilyAndEmbeddedSource(): void
    {
        $face = new AssetEmbedder()->fontFace('rp-heading', self::FONT);

        self::assertStringContainsString('@font-face', $face);
        self::assertStringContainsString("font-family:'rp-heading'", $face);
        self::assertStringContainsString('data:font/ttf;base64,', $face);
        self::assertStringContainsString("format('truetype')", $face);
    }

    public function testImageDataUriReturnsNullForMissingFile(): void
    {
        self::assertNull(new AssetEmbedder()->imageDataUri(__DIR__.'/does-not-exist.png'));
    }

    public function testImageDataUriEmbedsAPngWithItsMime(): void
    {
        $base = (string) tempnam(sys_get_temp_dir(), 'pw-embed-');
        $png = $base.'.png';
        imagepng(imagecreatetruecolor(4, 4), $png);

        $uri = new AssetEmbedder()->imageDataUri($png);

        self::assertNotNull($uri);
        self::assertStringStartsWith('data:image/png;base64,', $uri);

        @unlink($base);
        @unlink($png);
    }

    public function testImageDataUriDetectsWebpMimeFromExtension(): void
    {
        $base = (string) tempnam(sys_get_temp_dir(), 'pw-embed-');
        $webp = $base.'.webp';
        file_put_contents($webp, 'RIFF____WEBPfake');

        $uri = new AssetEmbedder()->imageDataUri($webp);

        self::assertNotNull($uri);
        self::assertStringStartsWith('data:image/webp;base64,', $uri);

        @unlink($base);
        @unlink($webp);
    }
}
