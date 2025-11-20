<?php

namespace Pushword\Core\Utils;

final class ImageRatioLabeler
{
    /**
     * @var array<string, array{int, int}>
     */
    private const FORMATS = [
        '16:9' => [16, 9],
        '9:16' => [9, 16],
        '4:3' => [4, 3],
        '3:4' => [3, 4],
        '3:2' => [3, 2],
        '2:3' => [2, 3],
        '1:1' => [1, 1],
    ];

    public static function fromDimensions(?int $width, ?int $height): string
    {
        if (null === $width || null === $height || 0 === $width || 0 === $height) {
            return '';
        }

        if ($width === $height) {
            return '1:1';
        }

        $targetRatio = $width / $height;
        $closestLabel = '';
        $closestDelta = \INF;

        foreach (self::FORMATS as $label => [$referenceWidth, $referenceHeight]) {
            $referenceRatio = $referenceWidth / $referenceHeight;
            $delta = abs($targetRatio - $referenceRatio);

            if ($delta >= $closestDelta) {
                continue;
            }

            $closestDelta = $delta;
            $closestLabel = $label;
        }

        return $closestLabel;
    }
}
