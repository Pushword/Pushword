<?php

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\License\Cbor;

/**
 * The vectors are RFC 8949 appendix A, which is the point of using them: the decoder
 * exists to read somebody else's bytes, so it is checked against the specification's
 * own examples rather than against what our fixture happens to write.
 */
final class CborTest extends TestCase
{
    public function testUnsignedIntegersAcrossEveryWidth(): void
    {
        self::assertSame(0, Cbor::decode("\x00"));
        self::assertSame(10, Cbor::decode("\x0a"));
        self::assertSame(25, Cbor::decode("\x18\x19"));
        self::assertSame(1000, Cbor::decode("\x19\x03\xe8"));
        self::assertSame(1000000, Cbor::decode("\x1a\x00\x0f\x42\x40"));
    }

    public function testNegativeIntegers(): void
    {
        self::assertSame(-1, Cbor::decode("\x20"));
        self::assertSame(-500, Cbor::decode("\x39\x01\xf3"));
    }

    public function testTextAndByteStrings(): void
    {
        self::assertSame('IETF', Cbor::decode("\x64\x49\x45\x54\x46"));
        self::assertSame("\x01\x02\x03\x04", Cbor::decode("\x44\x01\x02\x03\x04"));
        self::assertSame('', Cbor::decode("\x60"));
    }

    public function testUtf8SurvivesIntact(): void
    {
        self::assertSame('Élie-Eynaud', Cbor::decode(ImageMetadataFixture::cborText('Élie-Eynaud')));
    }

    public function testArraysAndMaps(): void
    {
        self::assertSame([1, 2, 3], Cbor::decode("\x83\x01\x02\x03"));
        self::assertSame(['a' => 1, 'b' => [2, 3]], Cbor::decode("\xa2\x61\x61\x01\x61\x62\x82\x02\x03"));
        self::assertSame([], Cbor::decode("\x80"));
    }

    public function testSimpleValues(): void
    {
        self::assertFalse(Cbor::decode("\xf4"));
        self::assertTrue(Cbor::decode("\xf5"));
        self::assertNull(Cbor::decode("\xf6"));
    }

    public function testFloats(): void
    {
        self::assertSame(1.0, Cbor::decode("\xf9\x3c\x00"));
        self::assertSame(1.5, Cbor::decode("\xf9\x3e\x00"));
        self::assertSame(100000.0, Cbor::decode("\xfa\x47\xc3\x50\x00"));
        self::assertSame(1.1, Cbor::decode("\xfb\x3f\xf1\x99\x99\x99\x99\x99\x9a"));
    }

    /** A tag decorates the next item; C2PA wraps its timestamps in tag 0. */
    public function testATagIsTransparent(): void
    {
        self::assertSame('2026-07-26T00:00:00Z', Cbor::decode("\xc0".ImageMetadataFixture::cborText('2026-07-26T00:00:00Z')));
    }

    public function testIndefiniteLengthItems(): void
    {
        self::assertSame([1, 2], Cbor::decode("\x9f\x01\x02\xff"));
        self::assertSame('abcd', Cbor::decode("\x7f\x62\x61\x62\x62\x63\x64\xff"));
        self::assertSame(['a' => 1], Cbor::decode("\xbf\x61\x61\x01\xff"));
    }

    public function testTruncatedInputIsRejectedRatherThanGuessed(): void
    {
        self::assertNull(Cbor::decode(''));
        // Claims four bytes of text, supplies one.
        self::assertNull(Cbor::decode("\x64\x49"));
        // Claims three array items, supplies two.
        self::assertNull(Cbor::decode("\x83\x01\x02"));
        self::assertNull(Cbor::decode("\x19\x03"));
    }

    /**
     * The length fields come from an uploaded file. A map claiming four billion entries
     * must not be allocated before the bytes backing it are checked.
     */
    public function testAnAbsurdLengthDoesNotAllocate(): void
    {
        self::assertNull(Cbor::decode("\x9a\xff\xff\xff\xff"));
        self::assertNull(Cbor::decode("\xba\xff\xff\xff\xff"));
        self::assertNull(Cbor::decode("\x5a\xff\xff\xff\xff"));
    }

    /** Nesting is bounded so a crafted file cannot recurse the parser to death. */
    public function testDeepNestingIsAbandonedNotFollowed(): void
    {
        self::assertNull(Cbor::decode(str_repeat("\x81", 2000)."\x01"));
    }
}
