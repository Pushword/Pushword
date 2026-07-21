<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\ChromiumRasterizer;

#[Group('integration')]
final class ChromiumRasterizerTest extends TestCase
{
    /**
     * A configured-but-broken binary must degrade to null (the API turns that
     * into its 501), never throw or return garbage.
     */
    public function testBrokenBinaryDegradesToNull(): void
    {
        $rasterizer = new ChromiumRasterizer('/nonexistent/chromium-binary');

        self::assertNull($rasterizer->rasterize('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>', 10, 10));
    }
}
