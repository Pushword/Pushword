<?php

namespace Pushword\Core\Tests\Utils;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\LastTime;

class LastTimeTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir().'/pushword_last_time_test_'.uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testGetReturnsNullWhenFileDoesNotExist(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        self::assertNull($lastTime->get());
    }

    public function testGetReturnsDefaultWhenFileDoesNotExist(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $result = $lastTime->get('2020-01-01');
        self::assertNotNull($result);
        self::assertSame('2020', $result->format('Y'));
    }

    public function testSafeGetReturnsDefault(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $result = $lastTime->safeGet('2020-01-01');
        self::assertSame('2020', $result->format('Y'));
    }

    public function testSetWasRunCreatesFile(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $lastTime->setWasRun();
        self::assertFileExists($this->tmpFile);
    }

    public function testGetReturnsDateTimeAfterSetWasRun(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $lastTime->setWasRun();

        $result = $lastTime->get();
        self::assertNotNull($result);
    }

    public function testWasRunSinceReturnsFalseWhenNeverRun(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        self::assertFalse($lastTime->wasRunSince(new DateInterval('PT1H')));
    }

    public function testWasRunSinceReturnsTrueWhenJustRun(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $lastTime->setWasRun();
        self::assertTrue($lastTime->wasRunSince(new DateInterval('PT1H')));
    }

    public function testWasRunSinceReturnsFalseWhenRunLongAgo(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $lastTime->setWasRun('2000-01-01');
        self::assertFalse($lastTime->wasRunSince(new DateInterval('PT1H')));
    }

    public function testSetIsAliasForSetWasRun(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $lastTime->set();
        self::assertFileExists($this->tmpFile);
        self::assertNotNull($lastTime->get());
    }

    public function testSetWasRunWithSetIfNotExistFalse(): void
    {
        $lastTime = new LastTime($this->tmpFile);

        $lastTime->setWasRun('now', false);
        self::assertFileDoesNotExist($this->tmpFile);
    }
}
