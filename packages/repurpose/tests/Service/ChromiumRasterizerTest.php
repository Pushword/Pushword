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

    /**
     * A binary that never exits is the one failure Process reports by throwing
     * (ProcessTimedOutException) rather than by an exit code — every broken-binary
     * case above just exits non-zero. Seen for real on CI runners, where a hung
     * Chromium turned the preview endpoint's documented 501 into a 500.
     */
    public function testHangingBinaryDegradesToNull(): void
    {
        $binary = sys_get_temp_dir().'/pw-hanging-chromium-'.bin2hex(random_bytes(6));
        file_put_contents($binary, "#!/bin/sh\nsleep 30\n");
        chmod($binary, 0o700);

        try {
            $rasterizer = new ChromiumRasterizer($binary, 1);

            self::assertNull($rasterizer->rasterize('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>', 10, 10));
        } finally {
            @unlink($binary);
        }
    }
}
