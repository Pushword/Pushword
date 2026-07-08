<?php

namespace Pushword\Core\Tests\Service\EditorNotice;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;

final class TwigErrorMarkerTest extends TestCase
{
    public function testRoundTripsASimpleMessage(): void
    {
        $marker = TwigErrorMarker::for('Unknown "foo" function.');

        self::assertStringContainsString('pushword:twig-error', $marker);
        self::assertSame(['Unknown "foo" function.'], TwigErrorMarker::extractMessages($marker));
    }

    public function testCollapsesWhitespaceToKeepTheMarkerOnASingleLine(): void
    {
        $marker = TwigErrorMarker::for("line one\n  line two");

        self::assertStringNotContainsString("\n", $marker);
        self::assertSame(['line one line two'], TwigErrorMarker::extractMessages($marker));
    }

    /**
     * A hostile message must not close the comment early or inject markup: the
     * only `-->` in the output is the terminator, and the value round-trips.
     */
    public function testEscapesMessageSoItCannotBreakOutOfTheComment(): void
    {
        $marker = TwigErrorMarker::for('x"--><script>alert(1)</script>');

        self::assertStringNotContainsString('<script', $marker);
        self::assertSame(\strlen($marker) - 3, strrpos($marker, '-->'), 'the only "-->" must be the terminator');
        self::assertSame(['x"--><script>alert(1)</script>'], TwigErrorMarker::extractMessages($marker));
    }

    public function testExtractsEveryMarkerAndNothingElse(): void
    {
        $html = '<p>ok</p>'
            .TwigErrorMarker::for('first error')
            .'<p><!-- unrelated comment --></p>'
            .TwigErrorMarker::for('second error');

        self::assertSame(['first error', 'second error'], TwigErrorMarker::extractMessages($html));
    }

    public function testReturnsEmptyWhenNoMarkerPresent(): void
    {
        self::assertSame([], TwigErrorMarker::extractMessages('<p>nothing here</p>'));
    }
}
