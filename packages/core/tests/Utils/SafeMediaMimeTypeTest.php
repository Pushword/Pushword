<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\SafeMediaMimeType;

final class SafeMediaMimeTypeTest extends TestCase
{
    public function testGetReturnsConstantKeys(): void
    {
        self::assertSame(array_keys(SafeMediaMimeType::GET), SafeMediaMimeType::get());
    }

    public function testGetContainsExpectedMimeTypes(): void
    {
        $result = SafeMediaMimeType::get();

        self::assertContains('image/svg+xml', $result);
        self::assertContains('application/gpx+xml', $result);
        self::assertContains('application/gpx', $result);
    }
}
