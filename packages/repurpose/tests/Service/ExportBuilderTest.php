<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\ExportBuilder;
use ZipArchive;

#[Group('integration')]
final class ExportBuilderTest extends TestCase
{
    private function png(int $w = 1080, int $h = 1350): string
    {
        $image = imagecreatetruecolor(max(1, $w), max(1, $h));
        $bg = imagecolorallocate($image, 11, 17, 32);
        imagefilledrectangle($image, 0, 0, $w, $h, false === $bg ? 0 : $bg);
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    public function testZipContainsSlidesCaptionAndPdf(): void
    {
        $builder = new ExportBuilder();
        $bytes = $builder->build([$this->png(), $this->png()], 'My caption', ['pushword', 'carousel'], true);

        $path = (string) tempnam(sys_get_temp_dir(), 'pw-export-test-');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }

        self::assertContains('slide-1.png', $names);
        self::assertContains('slide-2.png', $names);
        self::assertContains('caption.txt', $names);
        self::assertContains('carousel.pdf', $names);

        $caption = $zip->getFromName('caption.txt');
        self::assertStringContainsString('My caption', (string) $caption);
        self::assertStringContainsString('#pushword', (string) $caption);

        $zip->close();
        @unlink($path);
    }

    public function testSvgArchiveContainsRawSvgsAndCaptionButNoPdf(): void
    {
        $builder = new ExportBuilder();
        $svg1 = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
        $svg2 = '<svg xmlns="http://www.w3.org/2000/svg"><circle/></svg>';
        $bytes = $builder->buildSvgArchive([$svg1, $svg2], 'Vector caption', ['pushword']);

        $path = (string) tempnam(sys_get_temp_dir(), 'pw-export-svg-');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }

        self::assertContains('slide-1.svg', $names);
        self::assertContains('slide-2.svg', $names);
        self::assertContains('caption.txt', $names);
        // A vector bundle carries no rasterised artifacts.
        self::assertNotContains('carousel.pdf', $names);
        self::assertNotContains('slide-1.png', $names);

        // The SVGs are shipped verbatim, not re-encoded.
        self::assertSame($svg1, $zip->getFromName('slide-1.svg'));
        self::assertStringContainsString('Vector caption', (string) $zip->getFromName('caption.txt'));

        $zip->close();
        @unlink($path);
    }

    public function testPdfIsValidAndEmbedsOnePagePerSlide(): void
    {
        $builder = new ExportBuilder();
        $pdf = $builder->pdf([$this->png(), $this->png(), $this->png()]);

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
        self::assertStringContainsString('/DCTDecode', $pdf);
        self::assertStringContainsString('/Count 3', $pdf);
        // MediaBox from the pixel dimensions.
        self::assertStringContainsString('/MediaBox[0 0 1080 1350]', $pdf);
    }

    public function testPdfWithoutSlidesInZipIsSkipped(): void
    {
        $builder = new ExportBuilder();
        $bytes = $builder->build([], 'caption', [], true);

        $path = (string) tempnam(sys_get_temp_dir(), 'pw-export-empty-');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        self::assertFalse($zip->getFromName('carousel.pdf'));
        $zip->close();
        @unlink($path);
    }
}
