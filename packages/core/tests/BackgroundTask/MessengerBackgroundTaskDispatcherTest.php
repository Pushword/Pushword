<?php

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\MessengerBackgroundTaskDispatcher;
use Pushword\Core\BackgroundTask\RunCommandMessage;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class MessengerBackgroundTaskDispatcherTest extends TestCase
{
    private string $varDir;

    private ProcessOutputStorage $outputStorage;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/pushword-bgtask-'.getmypid();
        $this->outputStorage = new ProcessOutputStorage(new Filesystem(), $this->varDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testDispatchSendsMessageToBus(): void
    {
        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (RunCommandMessage $msg): bool => 'test-type' === $msg->processType
                // --env=test appended: the child must run in the env that dispatched it.
                && ['php', 'bin/console', 'pw:image:cache', 'photo.jpg', '--env=test'] === $msg->commandParts
                && 'pw:image:cache' === $msg->commandPattern))
            ->willReturnCallback(static fn (RunCommandMessage $msg): Envelope => new Envelope($msg));

        $this->dispatcher($bus)->dispatch('test-type', ['php', 'bin/console', 'pw:image:cache', 'photo.jpg'], 'pw:image:cache');
    }

    public function testDispatchSkipsWhenProcessAlreadyRunning(): void
    {
        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->dispatcher($bus, isRunning: true)
            ->dispatch('test-type', ['php', 'bin/console', 'pw:image:cache', 'photo.jpg'], 'pw:image:cache');
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

        $this->dispatcher($this->bus(), manager: $manager)
            ->dispatch('test-type', ['php', 'bin/console', 'test'], 'test');
    }

    /**
     * The reported bug: an enqueued job has no process to find, so a front-end
     * deducing its state from the PID file alone called it completed.
     */
    public function testDispatchRecordsTheWaitForATaskSomeoneFollows(): void
    {
        $this->outputStorage->setStatus('test-type', 'running');

        $this->dispatcher($this->bus())->dispatch('test-type', ['php', 'bin/console', 'pw:static'], 'pw:static');

        self::assertSame('queued', $this->outputStorage->getStatus('test-type'));
    }

    public function testDispatchLeavesNoStatusBehindForATaskNobodyFollows(): void
    {
        $this->dispatcher($this->bus())->dispatch('image-cache-abc', ['php', 'bin/console', 'pw:image:cache'], 'pw:image:cache');

        self::assertNull($this->outputStorage->getStatus('image-cache-abc'));
        self::assertFileDoesNotExist($this->varDir.'/image-cache-abc-status.txt');
    }

    public function testAMappedCommandIsStampedWithItsOwnTransport(): void
    {
        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(RunCommandMessage::class),
                self::callback(static function (array $stamps): bool {
                    self::assertCount(1, $stamps);
                    self::assertInstanceOf(TransportNamesStamp::class, $stamps[0]);
                    self::assertSame(['static'], $stamps[0]->getTransportNames());

                    return true;
                }),
            )
            ->willReturnCallback(static fn (RunCommandMessage $msg): Envelope => new Envelope($msg));

        $this->dispatcher($bus, transports: ['pw:static' => 'static'])
            ->dispatch('test-type', ['php', 'bin/console', 'pw:static'], 'pw:static');
    }

    public function testAnUnmappedCommandKeepsTheDefaultRouting(): void
    {
        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RunCommandMessage::class), [])
            ->willReturnCallback(static fn (RunCommandMessage $msg): Envelope => new Envelope($msg));

        $this->dispatcher($bus, transports: ['pw:static' => 'static'])
            ->dispatch('test-type', ['php', 'bin/console', 'pw:image:cache'], 'pw:image:cache');
    }

    private function bus(): MessageBusInterface
    {
        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static fn (RunCommandMessage $msg): Envelope => new Envelope($msg),
        );

        return $bus;
    }

    /** @param array<string, string> $transports */
    private function dispatcher(
        MessageBusInterface $bus,
        bool $isRunning = false,
        array $transports = [],
        ?BackgroundProcessManager $manager = null,
    ): MessengerBackgroundTaskDispatcher {
        if (null === $manager) {
            $manager = self::createStub(BackgroundProcessManager::class);
            $manager->method('getPidFilePath')->willReturn('/tmp/test.pid');
            $manager->method('getProcessInfo')->willReturn([
                'isRunning' => $isRunning,
                'startTime' => $isRunning ? time() : null,
                'pid' => $isRunning ? 12345 : null,
            ]);
        }

        return new MessengerBackgroundTaskDispatcher($bus, $manager, $this->outputStorage, 'test', $transports);
    }
}
