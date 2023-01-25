<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

/**
 * This test are planned to be executed only on Github Action.
 */
class AppTest extends TestCase
{
    public function testNewInstallation(): void
    {
        $this->assertFileExists(__DIR__.'/../src/DataFixtures/AppFixtures.php');
        // todo
    }
}
