<?php

namespace Pushword\Core\Utils;

final class ImageRatioLabeler
{
    /**
     * Maximum allowed deviation from a standard ratio (as a percentage of the reference ratio).
     * 5% tolerance means a 16:9 (1.778) image can range from ~1.689 to ~1.867.
     */
    private const float TOLERANCE = 0.05;

    /**
     * @var array<string, array{int, int}>
     */
    private const array FORMATS = [
        '21:9' => [21, 9],
        '16:9' => [16, 9],
        '3:2' => [3, 2],
        '4:3' => [4, 3],
        '5:4' => [5, 4],
        '1:1' => [1, 1],
        '4:5' => [4, 5],
        '3:4' => [3, 4],
        '2:3' => [2, 3],
        '9:16' => [9, 16],
        '9:21' => [9, 21],
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

        if ('' === $closestLabel) {
            return '';
        }

        [$refWidth, $refHeight] = self::FORMATS[$closestLabel];
        $referenceRatio = $refWidth / $refHeight;

        if ($closestDelta / $referenceRatio > self::TOLERANCE) {
            return '';
        }

        return $closestLabel;
    }
}
