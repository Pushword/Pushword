<?php

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\License\Bytes;

/**
 * Every bounds check in the container walkers rests on these returning null rather than
 * a partial or negative value, so the out-of-range cases matter as much as the happy ones.
 */
final class BytesTest extends TestCase
{
    public function testEachWidthReadsItsValue(): void
    {
        self::assertSame(0xAB, Bytes::uint8("\xAB"));
        self::assertSame(0xABCD, Bytes::uint16("\xAB\xCD"));
        self::assertSame(0xABCDEF01, Bytes::uint32("\xAB\xCD\xEF\x01"));
        self::assertSame(0x0102, Bytes::uint64("\x00\x00\x00\x00\x00\x00\x01\x02"));
    }

    /** RIFF is the only little-endian container here, hence the separate reader. */
    public function testLittleEndianIsTheMirrorOfBigEndian(): void
    {
        self::assertSame(1, Bytes::uint32Le("\x01\x00\x00\x00"));
        self::assertSame(1, Bytes::uint32("\x00\x00\x00\x01"));
        self::assertSame(0x04030201, Bytes::uint32Le("\x01\x02\x03\x04"));
    }

    public function testTheOffsetIsHonoured(): void
    {
        $bytes = "\xFF\xFF\x00\x2A";

        self::assertSame(42, Bytes::uint16($bytes, 2));
        self::assertSame(0xFF, Bytes::uint8($bytes, 1));
    }

    public function testReadingPastTheEndYieldsNullNotAPartialValue(): void
    {
        self::assertNull(Bytes::uint32("\x01\x02\x03"));
        self::assertNull(Bytes::uint16("\x01"));
        self::assertNull(Bytes::uint8(''));
        self::assertNull(Bytes::uint64("\x01\x02\x03\x04\x05\x06\x07"));
        // In range on its own, past the end once the offset is applied.
        self::assertNull(Bytes::uint32("\x01\x02\x03\x04", 1));
    }

    public function testANegativeOffsetIsRejected(): void
    {
        self::assertNull(Bytes::uint16("\x01\x02", -1));
    }

    /** Reading the very last byte available is in range, not off the end. */
    public function testTheFinalByteIsStillReadable(): void
    {
        self::assertSame(0x02, Bytes::uint8("\x01\x02", 1));
        self::assertSame(0x0304, Bytes::uint16("\x01\x02\x03\x04", 2));
    }

    /**
     * A 64-bit length above PHP_INT_MAX comes back from unpack() as a negative int. It
     * has to read as "no value": treated as a length it would pass every `$x > $length`
     * bounds check in the walkers and turn them into no-ops.
     */
    public function testAValueWiderThanPhpsSignedIntIsRefused(): void
    {
        self::assertNull(Bytes::uint64("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF"));
        self::assertNull(Bytes::uint64("\x80\x00\x00\x00\x00\x00\x00\x00"));

        // One below the sign boundary is still a real number.
        self::assertSame(\PHP_INT_MAX, Bytes::uint64("\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF"));
    }

    /** The widest unsigned 32-bit value stays positive on a 64-bit build. */
    public function testTheLargestUint32IsNotMistakenForNegative(): void
    {
        self::assertSame(4294967295, Bytes::uint32("\xFF\xFF\xFF\xFF"));
        self::assertSame(4294967295, Bytes::uint32Le("\xFF\xFF\xFF\xFF"));
    }

    public function testZeroIsAValueNotAnAbsence(): void
    {
        self::assertSame(0, Bytes::uint8("\x00"));
        self::assertSame(0, Bytes::uint32("\x00\x00\x00\x00"));
    }
}
