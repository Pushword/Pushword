<?php

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    /**
     * A skipped warm-up used to leave no trace at all — weeks later, silently
     * missing image derivatives are indistinguishable from any other cause.
     * The skip stays (it is the dedup working) but goes on the record.
     */
    public function testAlreadyRunningSkipIsLogged(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->method('startBackgroundProcess')
            ->willThrowException(new ProcessAlreadyRunningException('already running'));

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Background task skipped, same task already running', self::callback(
                static fn (array $context): bool => 'img' === $context['processType']
            ));

        new ProcessBackgroundTaskDispatcher($manager, 'test', $logger)
            ->dispatch('img', ['php', 'bin/console', 'pw:image:cache', 'a.jpg'], 'pw:image:cache');
    }

    public function testLaunchFailureIsLoggedBeforePropagating(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->method('startBackgroundProcess')
            ->willThrowException(new RuntimeException('nohup failed'));

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with('Background task failed to launch', self::callback(
                static fn (array $context): bool => 'nohup failed' === $context['error']
            ));

        $this->expectException(RuntimeException::class);

        new ProcessBackgroundTaskDispatcher($manager, 'test', $logger)
            ->dispatch('img', ['php', 'bin/console', 'pw:image:cache', 'a.jpg'], 'pw:image:cache');
    }
}
