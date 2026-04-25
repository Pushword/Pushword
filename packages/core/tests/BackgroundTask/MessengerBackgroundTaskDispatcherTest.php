<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\MessengerBackgroundTaskDispatcher;
use Pushword\Core\BackgroundTask\RunCommandMessage;
use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerBackgroundTaskDispatcherTest extends TestCase
{
    public function testDispatchSendsMessageToBus(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->method('getProcessInfo')->willReturn([
            'isRunning' => false,
            'startTime' => null,
            'pid' => null,
        ]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (RunCommandMessage $msg): bool => 'test-type' === $msg->processType
                && ['php', 'bin/console', 'pw:image:cache', 'photo.jpg'] === $msg->commandParts
                && 'pw:image:cache' === $msg->commandPattern))
            ->willReturnCallback(static fn (RunCommandMessage $msg): Envelope => new Envelope($msg));

        $dispatcher = new MessengerBackgroundTaskDispatcher($bus, $manager);
        $dispatcher->dispatch('test-type', ['php', 'bin/console', 'pw:image:cache', 'photo.jpg'], 'pw:image:cache');
    }

    public function testDispatchSkipsWhenProcessAlreadyRunning(): void
    {
        $manager = self::createStub(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->method('getProcessInfo')->willReturn([
            'isRunning' => true,
            'startTime' => time(),
            'pid' => 12345,
        ]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $dispatcher = new MessengerBackgroundTaskDispatcher($bus, $manager);
        $dispatcher->dispatch('test-type', ['php', 'bin/console', 'pw:image:cache', 'photo.jpg'], 'pw:image:cache');
    }

    public function testDispatchCleansUpStaleProcessBeforeChecking(): void
    {
        $manager = self::createMock(BackgroundProcessManager::class);
        $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
        $manager->expects(self::once())
            ->method('cleanupStaleProcess')
            ->with('/tmp/test.pid');
        $manager->method('getProcessInfo')->willReturn([
            'isRunning' => false,
            'startTime' => null,
            'pid' => null,
        ]);

        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static fn (RunCommandMessage $msg): Envelope => new Envelope($msg),
        );

        $dispatcher = new MessengerBackgroundTaskDispatcher($bus, $manager);
        $dispatcher->dispatch('test-type', ['php', 'bin/console', 'test'], 'test');
    }
}
