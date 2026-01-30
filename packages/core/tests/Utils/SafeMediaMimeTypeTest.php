<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\SafeMediaMimeType;

class SafeMediaMimeTypeTest extends TestCase
{
    public function testGetReturnsNonEmptyArray(): void
    {
        $result = SafeMediaMimeType::get();

        self::assertNotEmpty($result);
    }

    public function testGetContainsExpectedMimeTypes(): void
    {
        $result = SafeMediaMimeType::get();

        self::assertContains('image/svg+xml', $result);
        self::assertContains('application/gpx+xml', $result);
        self::assertContains('application/gpx', $result);
    }

    public function testGetReturnsOnlyKeys(): void
    {
        $result = SafeMediaMimeType::get();

        self::assertSame(array_keys(SafeMediaMimeType::GET), $result);
    }
}
