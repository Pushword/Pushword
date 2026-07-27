<?php

namespace Pushword\Core\Image\License;

use RuntimeException;
use SplFileObject;

/**
 * The metadata payloads an image carries, pulled straight out of its container.
 *
 * Imagick used to do this, but `pingImage()` — the only variant that does not decode
 * pixels — exposes profiles for JPEG alone: PNG and WebP came back empty whatever they
 * held, and `readImage()` costs ~23 ms against ~0.2 ms on a 1536x1024 PNG, which the
 * upload listener and `pw:media:license` both pay per file. Walking the container
 * ourselves is the same order of magnitude as ping, uniform across the three formats
 * that can carry metadata, and drops ext-imagick from the requirement.
 *
 * GIF is the fourth format Pushword accepts and is deliberately absent: its Application
 * Extension can technically hold XMP, and nothing writes it there.
 */
final readonly class ImageContainer
{
    /**
     * A packet larger than this is not metadata anybody wrote. The cap exists because
     * the length fields are attacker-controlled — an uploaded file can claim a 2 GB
     * text chunk, and we would allocate it before ever looking at the content.
     */
    private const int MAX_PAYLOAD = 16 * 1024 * 1024;

    private const string XMP_SIGNATURE = "http://ns.adobe.com/xap/1.0/\0";

    /** ImageMagick writes XMP into PNG under its own keyword, in its own hex wrapper. */
    private const string PNG_RAW_PROFILE_KEYWORD = 'Raw profile type xmp';

    private const string PNG_XMP_KEYWORD = 'XML:com.adobe.xmp';

    private function __construct(
        public string $xmp,
        public string $c2pa,
    ) {
    }

    public static function read(string $path): self
    {
        if (! is_file($path)) {
            return new self('', '');
        }

        try {
            return self::walk(new SplFileObject($path, 'rb'));
        } catch (RuntimeException) {
            // Unreadable: permissions, or the file vanished between the check and here.
            return new self('', '');
        }
    }

    private static function walk(SplFileObject $file): self
    {
        $magic = self::readBytes($file, 12);

        if (str_starts_with($magic, "\xFF\xD8")) {
            return self::walkJpeg($file);
        }

        if (str_starts_with($magic, "\x89PNG\r\n\x1A\n")) {
            return self::walkPng($file);
        }

        if (str_starts_with($magic, 'RIFF') && str_starts_with(substr($magic, 8), 'WEBP')) {
            return self::walkWebp($file);
        }

        return new self('', '');
    }

    // --- JPEG ---

    /**
     * Segments are walked rather than the bytes scanned: a JPEG carries two APP1
     * segments (EXIF then XMP) so taking the first is wrong, and an EXIF thumbnail is
     * itself a JPEG that may hold its own packet.
     */
    private static function walkJpeg(SplFileObject $file): self
    {
        $file->fseek(2);
        $xmp = '';
        $jumbf = [];

        while (true) {
            $marker = self::readBytes($file, 2);
            if (2 !== \strlen($marker) || "\xFF" !== $marker[0]) {
                break;
            }

            $kind = \ord($marker[1]);

            // Entropy-coded data starts here and carries no length, so nothing past it
            // can be walked. Every metadata segment precedes it.
            if (0xDA === $kind || 0xD9 === $kind) {
                break;
            }

            // Standalone markers: no length field follows.
            if (0x01 === $kind) {
                continue;
            }

            if (0xD0 <= $kind && $kind <= 0xD7) {
                continue;
            }

            $length = self::readUint16($file);
            if (null === $length || $length < 2) {
                break;
            }

            $payloadLength = $length - 2;

            if (0xE1 === $kind && '' === $xmp) {
                $payload = self::readBytes($file, $payloadLength);
                if (str_starts_with($payload, self::XMP_SIGNATURE)) {
                    $xmp = substr($payload, \strlen(self::XMP_SIGNATURE));
                }

                continue;
            }

            if (0xEB === $kind) {
                $jumbf[] = self::readBytes($file, $payloadLength);

                continue;
            }

            $file->fseek($payloadLength, \SEEK_CUR);
        }

        return new self($xmp, self::reassembleApp11($jumbf));
    }

    /**
     * ISO 19566-5 splits one JUMBF superbox across APP11 segments, each prefixed with
     * `JP`, a box instance number and a packet sequence number, and each repeating the
     * superbox LBox/TBox. Only the first fragment's header is kept.
     *
     * Built from the specification: no C2PA-carrying JPEG was available to check it
     * against, and ImageMagick drops the manifest when transcoding one from PNG.
     *
     * @param string[] $segments
     */
    private static function reassembleApp11(array $segments): string
    {
        $fragments = [];

        foreach ($segments as $segment) {
            // 'JP' + instance (2) + sequence (4) + LBox (4) + TBox (4)
            if (\strlen($segment) < 16) {
                continue;
            }

            if (! str_starts_with($segment, 'JP')) {
                continue;
            }

            $instance = Bytes::uint16($segment, 2);
            $sequence = Bytes::uint32($segment, 4);
            if (null === $instance) {
                continue;
            }

            if (null === $sequence) {
                continue;
            }

            $fragments[$instance][$sequence] = substr($segment, 8);
        }

        if ([] === $fragments) {
            return '';
        }

        $first = reset($fragments);
        ksort($first);

        $manifest = '';
        foreach ($first as $index => $fragment) {
            // Every packet repeats the superbox header; it belongs to the stream once.
            $manifest .= 1 === $index ? $fragment : substr($fragment, 8);
        }

        return $manifest;
    }

    // --- PNG ---

    private static function walkPng(SplFileObject $file): self
    {
        $file->fseek(8);
        $xmp = '';
        $c2pa = '';

        while (true) {
            $length = self::readUint32($file);
            $type = self::readBytes($file, 4);

            if (null === $length || 4 !== \strlen($type) || 'IEND' === $type) {
                break;
            }

            if ('caBX' === $type && '' === $c2pa) {
                $c2pa = self::readBytes($file, $length);
                $file->fseek(4, \SEEK_CUR);

                continue;
            }

            if (\in_array($type, ['iTXt', 'zTXt', 'tEXt'], true) && '' === $xmp) {
                $xmp = self::pngTextXmp($type, self::readBytes($file, $length));
                $file->fseek(4, \SEEK_CUR);

                continue;
            }

            // Chunk data plus its CRC.
            $file->fseek($length + 4, \SEEK_CUR);
        }

        return new self($xmp, $c2pa);
    }

    private static function pngTextXmp(string $type, string $chunk): string
    {
        $separator = strpos($chunk, "\0");
        if (false === $separator) {
            return '';
        }

        $keyword = substr($chunk, 0, $separator);
        $rest = substr($chunk, $separator + 1);

        if (self::PNG_XMP_KEYWORD === $keyword) {
            // iTXt: compression flag, compression method, language tag, translated
            // keyword, then the text. tEXt holds the text directly.
            if ('iTXt' !== $type) {
                return $rest;
            }

            $compressed = str_starts_with($rest, "\x01");
            $rest = substr($rest, 2);

            foreach ([0, 1] as $ignored) {
                $end = strpos($rest, "\0");
                if (false === $end) {
                    return '';
                }

                $rest = substr($rest, $end + 1);
            }

            return $compressed ? self::inflate($rest) : $rest;
        }

        if (self::PNG_RAW_PROFILE_KEYWORD === $keyword) {
            return self::rawProfile('zTXt' === $type ? self::inflate(substr($rest, 1)) : $rest);
        }

        return '';
    }

    /**
     * ImageMagick's "raw profile" wrapper: a newline, the profile name, the byte length
     * right-aligned in eight columns, then the bytes as newline-wrapped hex.
     */
    private static function rawProfile(string $profile): string
    {
        $lines = explode("\n", trim($profile, "\n"));
        if (\count($lines) < 3) {
            return '';
        }

        $hex = str_replace(["\n", "\r", ' '], '', implode('', \array_slice($lines, 2)));
        if ('' === $hex || 1 === \strlen($hex) % 2) {
            return '';
        }

        $bytes = @hex2bin($hex);

        return false === $bytes ? '' : $bytes;
    }

    private static function inflate(string $payload): string
    {
        if ('' === $payload) {
            return '';
        }

        $inflated = @gzuncompress($payload, self::MAX_PAYLOAD);

        return false === $inflated ? '' : $inflated;
    }

    // --- WebP ---

    private static function walkWebp(SplFileObject $file): self
    {
        $file->fseek(12);
        $xmp = '';
        $c2pa = '';

        while (true) {
            $fourCc = self::readBytes($file, 4);
            $length = self::readUint32($file, littleEndian: true);

            if (4 !== \strlen($fourCc) || null === $length) {
                break;
            }

            if ('XMP ' === $fourCc && '' === $xmp) {
                $xmp = self::readBytes($file, $length);
            } elseif ('C2PA' === $fourCc && '' === $c2pa) {
                $c2pa = self::readBytes($file, $length);
            } else {
                $file->fseek($length, \SEEK_CUR);
            }

            // RIFF pads every odd-sized chunk to an even boundary.
            if (1 === $length % 2) {
                $file->fseek(1, \SEEK_CUR);
            }
        }

        return new self($xmp, $c2pa);
    }

    // --- primitives ---

    private static function readBytes(SplFileObject $file, int $length): string
    {
        if ($length <= 0 || $length > self::MAX_PAYLOAD) {
            return '';
        }

        $bytes = $file->fread($length);

        return false === $bytes ? '' : $bytes;
    }

    private static function readUint16(SplFileObject $file): ?int
    {
        return Bytes::uint16(self::readBytes($file, 2));
    }

    private static function readUint32(SplFileObject $file, bool $littleEndian = false): ?int
    {
        $bytes = self::readBytes($file, 4);

        return $littleEndian ? Bytes::uint32Le($bytes) : Bytes::uint32($bytes);
    }
}
