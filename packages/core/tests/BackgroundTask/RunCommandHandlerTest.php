<?php

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\RunCommandHandler;
use Pushword\Core\BackgroundTask\RunCommandMessage;
use Pushword\Core\Service\BackgroundProcessManager;

final class RunCommandHandlerTest extends TestCase
{
    public function testHandlerExecutesCommandAndCleansUp(): void
    {
        $manager = self::createMock(BackgroundProcessManager::class);
        $manager->expects(self::once())
            ->method('registerProcess')
            ->with('/tmp/test.pid', 'echo');
        $manager->expects(self::once())
            ->method('unregisterProcess')
            ->with('/tmp/test.pid');
        $manager->expects(self::once())
            ->method('getPidFilePath')
            ->with('test-type')
            ->willReturn('/tmp/test.pid');

        $handler = new RunCommandHandler($manager, '/tmp');
        $message = new RunCommandMessage('test-type', ['echo', 'hello'], 'echo');

        $handler($message);
    }

    public function testHandlerCleansUpOnFailure(): void
    {
        $manager = self::createMock(BackgroundProcessManager::class);
        $manager->expects(self::once())->method('registerProcess');
        $manager->expects(self::once())->method('unregisterProcess');
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');

        $handler = new RunCommandHandler($manager, '/tmp');
        // Command that will fail (non-existent)
        $message = new RunCommandMessage('test-type', ['false'], 'false');

        // Handler doesn't throw on process failure — it just runs and cleans up
        $handler($message);

        $this->addToAssertionCount(1);
    }
}
