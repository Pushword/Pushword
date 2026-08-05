<?php

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\ProcessBackgroundTaskDispatcher;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessAlreadyRunningException;
use RuntimeException;

final class ProcessBackgroundTaskDispatcherTest extends TestCase
{
    public function testAlreadyRunningIsSilenced(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->method('startBackgroundProcess')
            ->willThrowException(new ProcessAlreadyRunningException('already running'));

        $dispatcher = new ProcessBackgroundTaskDispatcher($manager, 'test');
        $dispatcher->dispatch('test', ['php', 'bin/console', 'test'], 'test');

        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testTheSpawnedCommandCarriesTheDispatchingEnvironment(): void
    {
        $manager = self::createMock(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->expects(self::once())
            ->method('startBackgroundProcess')
            ->with('/tmp/test.pid', ['php', 'bin/console', 'pw:image:optimize', '--env=test'], 'pw:image:optimize');

        new ProcessBackgroundTaskDispatcher($manager, 'test')
            ->dispatch('test', ['php', 'bin/console', 'pw:image:optimize'], 'pw:image:optimize');
    }

    public function testLaunchFailurePropagates(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->method('startBackgroundProcess')
            ->willThrowException(new RuntimeException('nohup failed'));

        $dispatcher = new ProcessBackgroundTaskDispatcher($manager, 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('nohup failed');

        $dispatcher->dispatch('test', ['php', 'bin/console', 'test'], 'test');
    }
}
