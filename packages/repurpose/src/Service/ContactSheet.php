<?php

namespace Pushword\Repurpose\Service;

/**
 * Composes a deck's slide SVGs into one numbered contact-sheet SVG, so an agent
 * (or the preview endpoint) can eyeball the whole carousel in a single artifact.
 * Slides are nested as inline `<svg>` cells scaled through their viewBox — their
 * ids are already unique per slide index, so no rewriting beyond the root
 * geometry attributes is needed.
 */
final readonly class ContactSheet
{
    public const int CELL_WIDTH = 540;

    private const int GAP = 24;

    private const int LABEL_HEIGHT = 30;

    private const int NOTE_HEIGHT = 34;

    private const int COLUMNS = 4;

    /**
     * @param list<string> $slideSvgs self-contained SVGs from {@see SlideRenderer::renderDeck()}
     * @param int          $cellWidth CSS px each slide is shown at — pass the network's mobile feed width so legibility is judged at the realistic worst case
     * @param string|null  $note      caption stating the calibration, drawn across the top of the sheet
     *
     * @return array{svg: string, width: int, height: int}
     */
    public function build(array $slideSvgs, int $slideWidth, int $slideHeight, int $cellWidth = self::CELL_WIDTH, ?string $note = null): array
    {
        $count = \count($slideSvgs);
        $columns = max(1, min(self::COLUMNS, $count));
        $rows = (int) ceil($count / $columns);
        $cellHeight = (int) round($cellWidth * $slideHeight / max(1, $slideWidth));

        $top = null === $note ? 0 : self::NOTE_HEIGHT;
        $width = self::GAP + $columns * ($cellWidth + self::GAP);
        $height = $top + self::GAP + $rows * (self::LABEL_HEIGHT + $cellHeight + self::GAP);

        $body = '<rect width="'.$width.'" height="'.$height.'" fill="#f1f5f9"/>';
        if (null !== $note) {
            $body .= '<text x="'.self::GAP.'" y="24" font-family="sans-serif" font-size="15" fill="#475569">'
                .htmlspecialchars($note, \ENT_QUOTES | \ENT_XML1, 'UTF-8').'</text>';
        }

        $i = 0;
        foreach ($slideSvgs as $svg) {
            $x = self::GAP + ($i % $columns) * ($cellWidth + self::GAP);
            $y = $top + self::GAP + intdiv($i, $columns) * (self::LABEL_HEIGHT + $cellHeight + self::GAP);
            ++$i;

            $body .= '<text x="'.$x.'" y="'.($y + 20).'" font-family="sans-serif" font-size="16" fill="#475569">'.$i.'</text>';
            $body .= (string) preg_replace(
                '/^(<svg\b[^>]*?) width="\d+" height="\d+"/',
                '$1 x="'.$x.'" y="'.($y + self::LABEL_HEIGHT).'" width="'.$cellWidth.'" height="'.$cellHeight.'"',
                $svg,
                1,
            );
        }

        return [
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" width="'.$width.'" height="'.$height.'">'.$body.'</svg>',
            'width' => $width,
            'height' => $height,
        ];
    }
}
