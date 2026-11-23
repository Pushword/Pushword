<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Doctrine\CompressedJsonType;

final class CompressedJsonTypeTest extends TestCase
{
    public function testCompressesJsonAtLevelOneAndDecodesStringsAndStreams(): void
    {
        $type = new CompressedJsonType();
        $platform = new SQLitePlatform();
        $spec = [
            'caption' => str_repeat('Café / chalet ', 50),
            'slides' => [['title' => 'First slide', 'image' => ['zoom' => 1.0]]],
        ];
        $json = json_encode($spec, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
        $compressed = $type->convertToDatabaseValue($spec, $platform);

        self::assertLessThan(\strlen($json), \strlen($compressed));
        self::assertSame(gzcompress($json, 1), $compressed);
        self::assertSame($spec, $type->convertToPHPValue($compressed, $platform));

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, $compressed);
        rewind($stream);
        self::assertSame($spec, $type->convertToPHPValue($stream, $platform));
        fclose($stream);
    }

    public function testDeclaresABinaryColumnAndSupportsNullValues(): void
    {
        $type = new CompressedJsonType();
        $platform = new SQLitePlatform();

        self::assertSame('BLOB', $type->getSQLDeclaration([], $platform));
        self::assertSame(ParameterType::LARGE_OBJECT, $type->getBindingType());
        self::assertSame([null, null], [$type->convertToDatabaseValue(null, $platform), $type->convertToPHPValue(null, $platform)]);
    }

    #[TestWith(['Invalid zlib data'])]
    #[TestWith([42])]
    public function testRejectsAnInvalidCompressedValue(string|int $value): void
    {
        $this->expectException(ValueNotConvertible::class);

        new CompressedJsonType()->convertToPHPValue($value, new SQLitePlatform());
    }
}
