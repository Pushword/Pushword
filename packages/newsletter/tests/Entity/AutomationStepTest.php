<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Entity\AutomationStep;

final class AutomationStepTest extends TestCase
{
    public function testEditorNumbersKeepTheExistingDeliveryPositions(): void
    {
        $step = new AutomationStep();
        self::assertSame(1, $step->sequenceNumber);
        self::assertSame('Email 1', (string) $step);
        $step->sequenceNumber = 4;
        self::assertSame(3, $step->position);
        $step->position = 1;
        self::assertSame(2, $step->sequenceNumber);
        $step->subject = 'Welcome';
        self::assertSame('Welcome', (string) $step);
    }
}
