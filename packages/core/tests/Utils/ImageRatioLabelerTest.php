<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\ImageRatioLabeler;

class ImageRatioLabelerTest extends TestCase
{
    #[DataProvider('provideInvalidDimensions')]
    public function testReturnEmptyLabelWhenDimensionsMissing(?int $width, ?int $height): void
    {
        self::assertSame('', ImageRatioLabeler::fromDimensions($width, $height));
    }

    /**
     * @return iterable<array{?int, ?int}>
     */
    public static function provideInvalidDimensions(): iterable
    {
        yield [null, null];
        yield [100, null];
        yield [null, 100];
        yield [0, 100];
        yield [100, 0];
    }

    public function testSquareImageReturnsOneToOne(): void
    {
        self::assertSame('1:1', ImageRatioLabeler::fromDimensions(800, 800));
    }

    #[DataProvider('provideRatios')]
    public function testNearestNamedRatio(int $width, int $height, string $expectedLabel): void
    {
        self::assertSame($expectedLabel, ImageRatioLabeler::fromDimensions($width, $height));
    }

    /**
     * @return iterable<array{int, int, string}>
     */
    public static function provideRatios(): iterable
    {
        yield 'landscape 16:9' => [1920, 1080, '16:9'];
        yield 'portrait 9:16' => [1080, 1920, '9:16'];
        yield 'landscape 4:3' => [2000, 1500, '4:3'];
        yield 'portrait 3:4' => [1200, 1600, '3:4'];
        yield 'landscape close to 3:2' => [1500, 1000, '3:2'];
        yield 'portrait close to 2:3' => [1000, 1500, '2:3'];
    }
}
