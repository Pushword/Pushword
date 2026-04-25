<?php

namespace Pushword\Core\Utils;

use Normalizer;

final class SearchNormalizer
{
    private function __construct()
    {
    }

    public static function normalize(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
        if (! \is_string($normalized)) {
            $normalized = $value;
        }

        $normalized = preg_replace('/\p{Mn}+/u', '', $normalized);
        if (! \is_string($normalized)) {
            $normalized = $value;
        }

        return mb_strtolower($normalized);
    }
}
