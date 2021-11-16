<?php

namespace Pushword\Core\Utils;

class FilesizeFormatter
{
    /**
     * @param string|int|float $size
     */
    public static function formatBytes($size, int $precision = 2): string
    {
        $base = log((float) $size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(1024 ** ($base - floor($base)), $precision).' '.$suffixes[(int) floor($base)];
    }
}
