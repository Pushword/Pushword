<?php

namespace Pushword\Core\Utils;

class FilesizeFormatter
{
    public static function formatBytes(float|int|string $size, int $precision = 2): string
    {
        $size = (float) $size;
        if ($size <= 0) {
            return '0 ';
        }

        $base = log($size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(1024 ** ($base - floor($base)), $precision).' '.$suffixes[(int) floor($base)];
    }
}
