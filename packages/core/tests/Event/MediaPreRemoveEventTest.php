<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Event;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Event\MediaPreRemoveEvent;

final class MediaPreRemoveEventTest extends TestCase
{
    /** Every removal path asks; with no listener refusing, the media goes. */
    public function testAnUnansweredEventAllowsTheRemoval(): void
    {
        $event = new MediaPreRemoveEvent(new Media());

        self::assertFalse($event->isRefused());
        self::assertSame([], $event->getReasons());
    }

    /**
     * Every listener is asked, so the editor reads all the reasons at once instead of
     * fixing one only to meet the next.
     */
    public function testEveryRefusalIsKeptInOrder(): void
    {
        $event = new MediaPreRemoveEvent(new Media());

        $event->refuse('Used by the newsletter.');
        $event->refuse('Shown in the footer.');

        self::assertTrue($event->isRefused());
        self::assertSame(['Used by the newsletter.', 'Shown in the footer.'], $event->getReasons());
    }
}
