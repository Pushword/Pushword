<?php

namespace Pushword\Core\Utils;

class FilesizeFormatter
{
    public static function formatBytes($size, $precision = 2)
    {
        $base = log((float) $size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];

        return round(1024 ** ($base - floor($base)), $precision).' '.$suffixes[(int) floor($base)];
    }
}
