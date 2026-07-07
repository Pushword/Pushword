<?php

namespace Pushword\Core\Tests\Service\Markdown;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Markdown\BrokenImageComment;

final class BrokenImageCommentTest extends TestCase
{
    public function testRoundTripsASimpleSource(): void
    {
        $marker = BrokenImageComment::for('old-drupal-photo.jpg');

        self::assertStringContainsString('pushword:broken-image', $marker);
        self::assertSame(['old-drupal-photo.jpg'], BrokenImageComment::extractSources($marker));
    }

    /**
     * A hostile source must not close the comment early or inject markup: the
     * only `-->` in the output is the terminator, and the value round-trips.
     */
    public function testEscapesSourceSoItCannotBreakOutOfTheComment(): void
    {
        $marker = BrokenImageComment::for('x"--><script>alert(1)</script>');

        self::assertStringNotContainsString('<script', $marker);
        self::assertSame(\strlen($marker) - 3, strrpos($marker, '-->'), 'the only "-->" must be the terminator');
        self::assertSame(['x"--><script>alert(1)</script>'], BrokenImageComment::extractSources($marker));
    }

    public function testExtractsEveryMarkerAndNothingElse(): void
    {
        $html = '<p>ok</p>'
            .BrokenImageComment::for('a.jpg')
            .'<p><!-- unrelated comment --></p>'
            .BrokenImageComment::for('b.png');

        self::assertSame(['a.jpg', 'b.png'], BrokenImageComment::extractSources($html));
    }

    public function testReturnsEmptyWhenNoMarkerPresent(): void
    {
        self::assertSame([], BrokenImageComment::extractSources('<p>nothing here</p>'));
    }
}
