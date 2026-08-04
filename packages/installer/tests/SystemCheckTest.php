<?php

namespace Pushword\Installer\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\Installer\SystemCheck;

final class SystemCheckTest extends TestCase
{
    public function testNothingMissingRecommendsAgainstDocker(): void
    {
        $systemCheck = new SystemCheck([], dockerAvailable: true);

        self::assertTrue($systemCheck->shouldAsk());
        self::assertFalse($systemCheck->recommendsDocker());
        self::assertStringContainsString('already has everything', $systemCheck->reason());
    }

    public function testMissingExtensionsRecommendDockerAndAreNamedInTheReason(): void
    {
        $systemCheck = new SystemCheck(['gd', 'intl'], dockerAvailable: true);

        self::assertTrue($systemCheck->shouldAsk());
        self::assertTrue($systemCheck->recommendsDocker());
        self::assertStringContainsString('ext-gd, ext-intl', $systemCheck->reason());
    }

    /**
     * No daemon, no choice: install.php must not offer one, whichever way the
     * recommendation would have gone.
     */
    public function testWithoutDockerThereIsNothingToAsk(): void
    {
        self::assertFalse(new SystemCheck([], dockerAvailable: false)->shouldAsk());
        self::assertFalse(new SystemCheck(['intl'], dockerAvailable: false)->shouldAsk());
    }

    /**
     * The recommendation still has to be readable when Docker is absent: install.php
     * uses it to tell the user what to install instead.
     */
    public function testRecommendationIsIndependentOfDockerAvailability(): void
    {
        self::assertTrue(new SystemCheck(['intl'], dockerAvailable: false)->recommendsDocker());
    }

    /**
     * Guards the requirement list against drift: every extension SystemCheck asks for
     * is one the test suite's own PHP has, and the suite is the reference environment
     * documented in /installation.
     */
    public function testTheSuitesOwnPhpSatisfiesTheDocumentedRequirements(): void
    {
        self::assertSame([], SystemCheck::probe()->missing);
    }
}
