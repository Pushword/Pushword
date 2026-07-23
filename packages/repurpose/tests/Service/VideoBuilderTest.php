<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\VideoBuilder;
use RuntimeException;

/**
 * The failure paths, tested without a real ffmpeg so they hold on any CI runner:
 * a missing binary degrades to an honest "unavailable" the studio turns into an
 * install hint, and a hanging binary throws rather than wedging the request. The
 * successful encode is exercised end-to-end through the studio controller, which
 * asserts a real MP4 only when ffmpeg is actually present.
 */
#[Group('integration')]
final class VideoBuilderTest extends TestCase
{
    public function testAvailableIsFalseWhenTheConfiguredBinaryIsMissing(): void
    {
        self::assertFalse(new VideoBuilder('/nonexistent/ffmpeg-binary')->available());
    }

    public function testBuildThrowsWhenFfmpegIsUnavailable(): void
    {
        $this->expectException(RuntimeException::class);

        new VideoBuilder('/nonexistent/ffmpeg-binary')->build(['PNGBYTES'], 1080, 1350);
    }

    /**
     * A binary that never exits is the one failure Process reports by throwing
     * (ProcessTimedOutException) rather than by an exit code — it must surface as a
     * RuntimeException the controller turns into a 500, never a wedged request.
     */
    public function testBuildThrowsWhenTheBinaryHangs(): void
    {
        $binary = sys_get_temp_dir().'/pw-hanging-ffmpeg-'.bin2hex(random_bytes(6));
        file_put_contents($binary, "#!/bin/sh\nsleep 30\n");
        chmod($binary, 0o700);

        try {
            $this->expectException(RuntimeException::class);
            new VideoBuilder($binary, 1)->build(['PNGBYTES'], 1080, 1350);
        } finally {
            @unlink($binary);
        }
    }
}
