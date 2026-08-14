<?php

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\RunCommandHandler;
use Pushword\Core\BackgroundTask\RunCommandMessage;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Symfony\Component\Filesystem\Filesystem;

final class RunCommandHandlerTest extends TestCase
{
    /**
     * The command has to outlive the registration for the PID to be observable at
     * all — `getPid()` is null once the child has already exited.
     *
     * @var string[]
     */
    private const array SLOW_ENOUGH_COMMAND = ['sleep', '0.2'];

    private string $varDir;

    private ProcessOutputStorage $outputStorage;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/pushword-runcommand-'.getmypid();
        $this->outputStorage = new ProcessOutputStorage(new Filesystem(), $this->varDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testHandlerExecutesCommandAndCleansUp(): void
    {
        $manager = self::createMock(BackgroundProcessManager::class);
        $manager->expects(self::once())
            ->method('registerProcess')
            // The PID recorded is the command's, not the consumer's: liveness matches
            // the pattern against the recorded PID's command line, and a consumer's
            // line never names the command it runs.
            ->with('/tmp/test.pid', 'sleep', self::logicalNot(self::equalTo(getmypid())));
        $manager->expects(self::once())
            ->method('unregisterProcess')
            ->with('/tmp/test.pid');
        $manager->expects(self::once())
            ->method('getPidFilePath')
            ->with('test-type')
            ->willReturn('/tmp/test.pid');

        $handler = new RunCommandHandler($manager, $this->outputStorage, '/tmp');

        $handler(new RunCommandMessage('test-type', self::SLOW_ENOUGH_COMMAND, 'sleep'));
    }

    public function testHandlerCleansUpOnFailure(): void
    {
        $manager = self::createMock(BackgroundProcessManager::class);
        $manager->expects(self::once())->method('unregisterProcess');
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');

        $handler = new RunCommandHandler($manager, $this->outputStorage, '/tmp');
        // Command that will fail (non-existent)
        $message = new RunCommandMessage('test-type', ['false'], 'false');

        // Handler doesn't throw on process failure — it just runs and cleans up
        $handler($message);

        $this->addToAssertionCount(1);
    }

    public function testPickingUpAQueuedTaskEndsItsWait(): void
    {
        $this->outputStorage->setStatus('test-type', 'queued');

        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');

        new RunCommandHandler($manager, $this->outputStorage, '/tmp')(
            new RunCommandMessage('test-type', self::SLOW_ENOUGH_COMMAND, 'sleep'),
        );

        self::assertSame('running', $this->outputStorage->getStatus('test-type'));
    }

    public function testHandlerLeavesNoStatusBehindForATaskNobodyFollows(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');

        new RunCommandHandler($manager, $this->outputStorage, '/tmp')(
            new RunCommandMessage('image-cache-abc', self::SLOW_ENOUGH_COMMAND, 'sleep'),
        );

        self::assertNull($this->outputStorage->getStatus('image-cache-abc'));
    }
}
