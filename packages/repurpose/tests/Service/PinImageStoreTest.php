<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\PinImageStore;

#[Group('integration')]
final class PinImageStoreTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/pw-pin-'.bin2hex(random_bytes(4));
        mkdir($this->publicDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->publicDir.'/repurpose-pin/12.png');
        @rmdir($this->publicDir.'/repurpose-pin');
        @rmdir($this->publicDir);
    }

    public function testSaveWritesTheFileUnderThePublicRootAndReturnsItsUrlPath(): void
    {
        $store = new PinImageStore($this->publicDir);
        self::assertFalse($store->exists(12));

        $path = $store->save(12, 'PNGBYTES');

        self::assertSame('/repurpose-pin/12.png', $path);
        self::assertTrue($store->exists(12));
        self::assertSame($this->publicDir.'/repurpose-pin/12.png', $store->absolutePath(12));
        self::assertSame('PNGBYTES', file_get_contents($store->absolutePath(12)));
    }
}
