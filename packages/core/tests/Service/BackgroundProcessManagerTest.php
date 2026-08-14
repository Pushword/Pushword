<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Component\Filesystem\Filesystem;

final class BackgroundProcessManagerTest extends TestCase
{
    private string $varDir;

    private BackgroundProcessManager $manager;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/pw-bgm-test-'.uniqid();
        new Filesystem()->mkdir($this->varDir);
        $this->manager = new BackgroundProcessManager(new Filesystem(), $this->varDir, $this->varDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testCurrentProcessPidIsNotAlive(): void
    {
        $pid = (int) getmypid();
        self::assertFalse($this->manager->isProcessAlive($pid, ''));
    }

    public function testParentProcessPidIsNotAlive(): void
    {
        if (! \function_exists('posix_getppid')) {
            self::markTestSkipped('posix_getppid not available');
        }

        $ppid = posix_getppid();
        self::assertFalse($this->manager->isProcessAlive($ppid, ''));
    }

    public function testGetPidFilePathIncludesProcessType(): void
    {
        $path = $this->manager->getPidFilePath('static-generator--localhost.dev');
        self::assertStringEndsWith('/static-generator--localhost.dev.pid', $path);
    }

    public function testRegisterAndGetProcessInfo(): void
    {
        $pidFile = $this->manager->getPidFilePath('test-process');
        $this->manager->registerProcess($pidFile, 'test-cmd');

        $info = $this->manager->getProcessInfo($pidFile);
        // Current process PID is excluded by isProcessAlive, so isRunning is false
        self::assertFalse($info['isRunning']);
        self::assertIsInt($info['startTime']);
        self::assertSame((int) getmypid(), $info['pid']);

        $this->manager->unregisterProcess($pidFile);
        $info = $this->manager->getProcessInfo($pidFile);
        self::assertFalse($info['isRunning']);
    }

    /**
     * A consumer registering the command it just spawned has to record the child's
     * PID: liveness matches the pattern against that PID's command line, and the
     * consumer's own line never names the command it runs.
     */
    public function testRegisteringAChildRecordsItsPidRatherThanOurs(): void
    {
        $pidFile = $this->manager->getPidFilePath('test-child');
        $this->manager->registerProcess($pidFile, 'pw:static', 424242);

        self::assertSame(424242, $this->manager->getProcessInfo($pidFile)['pid']);

        $this->manager->unregisterProcess($pidFile);
    }

    public function testNonExistentPidFileReturnsNotRunning(): void
    {
        $info = $this->manager->getProcessInfo($this->varDir.'/nonexistent.pid');
        self::assertFalse($info['isRunning']);
        self::assertNull($info['startTime']);
        self::assertNull($info['pid']);
    }
}
