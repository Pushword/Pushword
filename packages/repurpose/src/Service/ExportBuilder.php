<?php

namespace Pushword\Repurpose\Service;

use RuntimeException;
use ZipArchive;

/**
 * Assembles an export from the slide PNGs the browser rasterised: a `.zip` of the
 * PNGs plus a `caption.txt`, and — for document networks — a multipage PDF.
 *
 * The PDF is written in pure PHP (no Imagick, no library): each PNG is transcoded
 * to JPEG with GD and embedded via `/DCTDecode`, one full-bleed image per page.
 * All pages share the deck's pixel size (a LinkedIn requirement) and the MediaBox
 * is set from those pixels. This runs on shared hosting and the FrankenPHP static
 * binary alike, where Imagick is absent.
 */
final class ExportBuilder
{
    /**
     * @param list<string> $slidePngs raw PNG bytes, in slide order
     * @param list<string> $hashtags
     *
     * @return string the raw bytes of a .zip archive
     */
    public function build(array $slidePngs, string $caption, array $hashtags, bool $withPdf): string
    {
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'pw-export-');
        $zip = new ZipArchive();
        if (true !== $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new RuntimeException('Cannot create export archive.');
        }

        foreach ($slidePngs as $i => $png) {
            $zip->addFromString('slide-'.($i + 1).'.png', $png);
        }

        $zip->addFromString('caption.txt', $this->captionFile($caption, $hashtags));

        if ($withPdf && [] !== $slidePngs) {
            $zip->addFromString('carousel.pdf', $this->pdf($slidePngs));
        }

        $zip->close();

        $bytes = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $bytes;
    }

    /**
     * @param list<string> $hashtags
     */
    private function captionFile(string $caption, array $hashtags): string
    {
        $out = $caption;
        if ([] !== $hashtags) {
            $out .= "\n\n".implode(' ', array_map(static fn (string $h): string => '#'.ltrim($h, '#'), $hashtags));
        }

        return $out."\n";
    }

    /**
     * A minimal multipage PDF: one full-page JPEG per slide.
     *
     * @param list<string> $pngs
     */
    public function pdf(array $pngs): string
    {
        $objects = [];
        $kids = [];
        $pageObjNums = [];

        // Reserve 1 = Catalog, 2 = Pages. Content objects start at 3.
        $next = 3;
        foreach ($pngs as $png) {
            [$jpeg, $width, $height] = $this->toJpeg($png);

            $imageNum = $next++;
            $contentNum = $next++;
            $pageNum = $next++;
            $pageObjNums[] = $pageNum;
            $kids[] = $pageNum.' 0 R';

            $objects[$imageNum] = '<</Type/XObject/Subtype/Image/Width '.$width.'/Height '.$height
                .'/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/DCTDecode/Length '.\strlen($jpeg).'>>'
                ."stream\n".$jpeg."\nendstream";

            $stream = 'q '.$width.' 0 0 '.$height.' 0 0 cm /Im Do Q';
            $objects[$contentNum] = '<</Length '.\strlen($stream).">>stream\n".$stream."\nendstream";

            $objects[$pageNum] = '<</Type/Page/Parent 2 0 R/MediaBox[0 0 '.$width.' '.$height.']'
                .'/Resources<</XObject<</Im '.$imageNum.' 0 R>>>>/Contents '.$contentNum.' 0 R>>';
        }

        $objects[1] = '<</Type/Catalog/Pages 2 0 R>>';
        $objects[2] = '<</Type/Pages/Kids['.implode(' ', $kids).']/Count '.\count($pageObjNums).'>>';

        return $this->assemble($objects);
    }

    /**
     * @param array<int, string> $objects object number => body
     */
    private function assemble(array $objects): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = \strlen($pdf);
            $pdf .= $num." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = \strlen($pdf);
        $count = \count($objects) + 1;
        $pdf .= "xref\n0 ".$count."\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; ++$i) {
            $pdf .= \sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        return $pdf.("trailer\n<</Size ".$count.'/Root 1 0 R>>'."\nstartxref\n".$xrefOffset."\n%%EOF");
    }

    /**
     * @return array{0: string, 1: int, 2: int} [jpeg bytes, width, height]
     */
    private function toJpeg(string $png): array
    {
        $image = @imagecreatefromstring($png);
        if (false === $image) {
            throw new RuntimeException('A slide image could not be decoded.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();
        imagejpeg($image, null, 92);
        $jpeg = ob_get_clean();

        return [$jpeg, $width, $height];
    }
}
