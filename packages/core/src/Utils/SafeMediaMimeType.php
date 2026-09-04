<?php

namespace Pushword\Core\Utils;

final class SafeMediaMimeType
{
    /**
     * Extensions accepted from every interactive upload entry point. The explicit
     * MIME lists make Symfony verify the bytes as well as the client filename.
     *
     * @var array<string, string|list<string>>
     */
    public const array EXTENSIONS = [
        'avif' => ['image/avif', 'image/avif-sequence'],
        'cr2' => ['image/x-canon-cr2', 'image/tiff'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'gif' => 'image/gif',
        'gpx' => ['application/gpx+xml', 'application/gpx', 'application/xml', 'text/xml', 'text/plain'],
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'mov' => 'video/quicktime',
        'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml'],
        'txt' => 'text/plain',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public const array GET = [
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
