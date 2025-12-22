<?php

namespace Pushword\AdvancedMainImage\Flat;

use Pushword\Flat\Converter\FlatPropertyConverterInterface;

/**
 * Converts mainImageFormat between integer (database) and label (flat file).
 */
final class MainImageFormatConverter implements FlatPropertyConverterInterface
{
    private const array INT_TO_LABEL = [
        0 => 'normal',
        1 => 'none',
        2 => '13fullscreen',
        3 => '34fullscreen',
    ];

    public function getPropertyName(): string
    {
        return 'mainImageFormat';
    }

    public function toFlatValue(mixed $value): mixed
    {
        if (! \is_int($value)) {
            return $value;
        }

        return self::INT_TO_LABEL[$value] ?? $value;
    }

    public function fromFlatValue(mixed $value): mixed
    {
        if (! \is_string($value)) {
            return $value;
        }

        $labelToInt = array_flip(self::INT_TO_LABEL);

        return $labelToInt[$value] ?? $value;
    }
}
