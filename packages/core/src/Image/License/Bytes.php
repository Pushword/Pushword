<?php

namespace Pushword\Core\Image\License;

/**
 * Fixed-width integers out of a binary string, typed.
 *
 * Container walking is all length fields, and `unpack()` hands back an `array<mixed>`
 * whatever the format string says, so every call site would otherwise have to prove the
 * result is an int. Reading past the end returns null rather than a partial value: the
 * bytes come from an uploaded file, so a truncated length must stop the walk.
 */
final class Bytes
{
    public static function uint8(string $bytes, int $offset = 0): ?int
    {
        return self::read($bytes, $offset, 1, 'C');
    }

    public static function uint16(string $bytes, int $offset = 0): ?int
    {
        return self::read($bytes, $offset, 2, 'n');
    }

    public static function uint32(string $bytes, int $offset = 0): ?int
    {
        return self::read($bytes, $offset, 4, 'N');
    }

    /** RIFF, and only RIFF, is little-endian. */
    public static function uint32Le(string $bytes, int $offset = 0): ?int
    {
        return self::read($bytes, $offset, 4, 'V');
    }

    public static function uint64(string $bytes, int $offset = 0): ?int
    {
        return self::read($bytes, $offset, 8, 'J');
    }

    private static function read(string $bytes, int $offset, int $width, string $format): ?int
    {
        if ($offset < 0 || $offset + $width > \strlen($bytes)) {
            return null;
        }

        $unpacked = unpack($format, substr($bytes, $offset, $width));
        if (false === $unpacked) {
            return null;
        }

        $value = $unpacked[1];

        // A 64-bit value above PHP_INT_MAX comes back negative. No real length is that
        // wide, and treating it as one would make every bounds check meaningless.
        return \is_int($value) && $value >= 0 ? $value : null;
    }
}
