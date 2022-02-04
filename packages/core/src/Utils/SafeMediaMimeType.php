<?php

namespace Pushword\Core\Utils;

class SafeMediaMimeType
{
    public const GET = [
        'application/gpx+xml' => 'gpx',
        'application/gpx' => 'gpx',
        'image/svg+xml' => 'svg',
    ];

    /**
     * @return string[]
     */
    public static function get(): array
    {
        return array_keys(self::GET);
    }
}
