<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Collaboration;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\LiveEditingState;

final class LiveEditingStateTest extends TestCase
{
    public function testRetainsTheOpeningAndRecentRevisionsUnderBothLimits(): void
    {
        $state = new LiveEditingState('page:1', '1');
        $state->remember('Opening');
        $state->snapshot = 'Small state';

        for ($revision = 2; $revision <= 70; ++$revision) {
            $state->revision = (string) $revision;
            $state->remember('Unused once a snapshot is stored');
        }

        self::assertNotNull($state->anchors);
        self::assertCount(64, $state->anchors);
        self::assertSame(['baseline' => 'Opening', 'snapshot' => null], $state->anchors[1]);
        self::assertArrayNotHasKey(2, $state->anchors);
        self::assertSame(['baseline' => null, 'snapshot' => 'Small state'], $state->anchors[70]);

        for ($revision = 71; $revision <= 74; ++$revision) {
            $state->snapshot = str_repeat('x', 1_400_000);
            $state->revision = (string) $revision;
            $state->remember('Unused');
        }

        self::assertCount(3, $state->anchors);
        self::assertSame([1, 73, 74], array_keys($state->anchors));

        $room = $state->room;
        $state->restart('80');
        $state->remember(str_repeat('x', 4_194_305));
        self::assertSame([], $state->anchors);
        self::assertNotSame($room, $state->room);
        self::assertSame('80', $state->revision);
    }
}
