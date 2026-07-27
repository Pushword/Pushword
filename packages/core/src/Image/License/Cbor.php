<?php

namespace Pushword\Core\Image\License;

/**
 * Just enough CBOR (RFC 8949) to read a C2PA assertion.
 *
 * C2PA stores its assertions as CBOR, and no CBOR decoder ships with PHP. This covers
 * the major types an assertion actually uses — integers, byte and text strings, arrays,
 * maps, tags and the simple values — and returns null for anything malformed rather
 * than throwing, because the input is an uploaded file.
 *
 * Indefinite-length items are decoded too: C2PA does not emit them, but a manifest
 * written by another tool is still somebody else's bytes.
 */
final class Cbor
{
    /** Deep enough for any assertion, shallow enough that a crafted file cannot recurse us to death. */
    private const int MAX_DEPTH = 32;

    private const int BREAK = 0xFF;

    /**
     * Decode the first CBOR item; null when the bytes are not valid CBOR.
     */
    public static function decode(string $bytes): mixed
    {
        $offset = 0;

        return self::item($bytes, $offset, 0);
    }

    private static function item(string $bytes, int &$offset, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH || $offset >= \strlen($bytes)) {
            return null;
        }

        $initial = \ord($bytes[$offset]);
        ++$offset;

        $major = $initial >> 5;
        $minor = $initial & 0x1F;

        // Indefinite length: the item runs until a break marker.
        if (31 === $minor) {
            return match ($major) {
                2, 3 => self::indefiniteString($bytes, $offset, $depth),
                4 => self::indefiniteArray($bytes, $offset, $depth),
                5 => self::indefiniteMap($bytes, $offset, $depth),
                default => null,
            };
        }

        $argument = self::argument($bytes, $offset, $minor);
        if (null === $argument) {
            return null;
        }

        return match ($major) {
            0 => $argument,
            1 => -1 - $argument,
            2, 3 => self::string($bytes, $offset, $argument),
            4 => self::listItems($bytes, $offset, $depth, $argument),
            5 => self::map($bytes, $offset, $depth, $argument),
            // A tag decorates the item that follows; the decoration is not what we read.
            6 => self::item($bytes, $offset, $depth + 1),
            // $major is `$initial >> 5` on a byte, so 0-7 is the whole domain.
            default => self::simple($minor, $argument),
        };
    }

    private static function argument(string $bytes, int &$offset, int $minor): ?int
    {
        if ($minor < 24) {
            return $minor;
        }

        $length = match ($minor) {
            24 => 1,
            25 => 2,
            26 => 4,
            27 => 8,
            default => 0,
        };

        if (0 === $length || $offset + $length > \strlen($bytes)) {
            return null;
        }

        $value = match ($length) {
            1 => Bytes::uint8($bytes, $offset),
            2 => Bytes::uint16($bytes, $offset),
            4 => Bytes::uint32($bytes, $offset),
            default => Bytes::uint64($bytes, $offset),
        };
        $offset += $length;

        return $value;
    }

    private static function string(string $bytes, int &$offset, int $length): ?string
    {
        if ($offset + $length > \strlen($bytes)) {
            return null;
        }

        $value = substr($bytes, $offset, $length);
        $offset += $length;

        return $value;
    }

    /**
     * @return list<mixed>|null
     */
    private static function listItems(string $bytes, int &$offset, int $depth, int $count): ?array
    {
        // A count larger than what is left cannot be honoured, and allocating for it is
        // exactly how a crafted length turns into a memory exhaustion.
        if ($count > \strlen($bytes) - $offset) {
            return null;
        }

        $items = [];
        for ($index = 0; $index < $count; ++$index) {
            $item = self::item($bytes, $offset, $depth + 1);
            if (null === $item) {
                return null;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function map(string $bytes, int &$offset, int $depth, int $count): ?array
    {
        if ($count > \strlen($bytes) - $offset) {
            return null;
        }

        $map = [];
        for ($index = 0; $index < $count; ++$index) {
            $key = self::item($bytes, $offset, $depth + 1);
            $value = self::item($bytes, $offset, $depth + 1);

            if (null === $value || ! \is_scalar($key)) {
                return null;
            }

            $map[(string) $key] = $value;
        }

        return $map;
    }

    private static function indefiniteString(string $bytes, int &$offset, int $depth): ?string
    {
        $value = '';

        while (! self::atBreak($bytes, $offset)) {
            $chunk = self::item($bytes, $offset, $depth + 1);
            if (! \is_string($chunk)) {
                return null;
            }

            $value .= $chunk;
        }

        ++$offset;

        return $value;
    }

    /**
     * @return list<mixed>|null
     */
    private static function indefiniteArray(string $bytes, int &$offset, int $depth): ?array
    {
        $items = [];

        while (! self::atBreak($bytes, $offset)) {
            $item = self::item($bytes, $offset, $depth + 1);
            if (null === $item) {
                return null;
            }

            $items[] = $item;
        }

        ++$offset;

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function indefiniteMap(string $bytes, int &$offset, int $depth): ?array
    {
        $map = [];

        while (! self::atBreak($bytes, $offset)) {
            $key = self::item($bytes, $offset, $depth + 1);
            $value = self::item($bytes, $offset, $depth + 1);

            if (null === $value || ! \is_scalar($key)) {
                return null;
            }

            $map[(string) $key] = $value;
        }

        ++$offset;

        return $map;
    }

    private static function atBreak(string $bytes, int $offset): bool
    {
        return $offset >= \strlen($bytes) || self::BREAK === \ord($bytes[$offset]);
    }

    private static function simple(int $minor, int $argument): bool|float|null
    {
        return match ($minor) {
            20 => false,
            21 => true,
            // null and undefined both mean "no value"; the caller cannot act on either.
            22, 23 => null,
            25, 26, 27 => self::float($minor, $argument),
            default => null,
        };
    }

    private static function float(int $minor, int $argument): ?float
    {
        if (25 === $minor) {
            return self::halfFloat($argument);
        }

        $unpacked = 26 === $minor
            ? unpack('G', pack('N', $argument))
            : unpack('E', pack('J', $argument));

        if (false === $unpacked) {
            return null;
        }

        return \is_float($unpacked[1]) ? $unpacked[1] : null;
    }

    /** IEEE 754 binary16, which PHP cannot unpack natively. */
    private static function halfFloat(int $bits): float
    {
        $sign = ($bits >> 15) & 0x1;
        $exponent = ($bits >> 10) & 0x1F;
        $fraction = $bits & 0x3FF;

        $value = match (true) {
            0 === $exponent => $fraction * 2 ** -24,
            31 === $exponent => 0 === $fraction ? \INF : \NAN,
            default => ($fraction + 1024) * 2 ** ($exponent - 25),
        };

        return 1 === $sign ? -$value : $value;
    }
}
