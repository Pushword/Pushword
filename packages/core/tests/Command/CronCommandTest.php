<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Command;

use DateTime;
use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Command\CronCommand;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\NullOutput;

class CronCommandTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/pw-cron-test-'.uniqid();
        mkdir($this->varDir);
    }

    protected function tearDown(): void
    {
        $lastRunFile = $this->varDir.'/pw-cron-last-run';
        if (file_exists($lastRunFile)) {
            unlink($lastRunFile);
        }

        rmdir($this->varDir);
    }

    public function testNoPublishCommandsConfigured(): void
    {
        $command = $this->makeCommand([], []);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testFirstRunInitializesTimestamp(): void
    {
        $command = $this->makeCommand([], [['command' => 'pw:static -i', 'on' => 'publish']]);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileExists($this->varDir.'/pw-cron-last-run');
    }

    public function testNoNewlyPublishedPagesDispatchesNothing(): void
    {
        // Initialize timestamp so it's not first run
        touch($this->varDir.'/pw-cron-last-run', (new DateTime('-1 hour'))->getTimestamp());

        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $command = $this->makeCommand([], [['command' => 'pw:static -i', 'on' => 'publish']], $dispatcher);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testNewlyPublishedPageDispatchesCommands(): void
    {
        // Initialize timestamp so it's not first run
        touch($this->varDir.'/pw-cron-last-run', (new DateTime('-1 hour'))->getTimestamp());

        $page = new Page();
        $page->setPublishedAt(new DateTime('-30 minutes'));

        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with('cron-publish', ['php', 'bin/console', 'pw:static', '-i'], 'pw:static -i');

        $command = $this->makeCommand([$page], [['command' => 'pw:static -i', 'on' => 'publish']], $dispatcher);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testOnlyCronEntriesAreIgnored(): void
    {
        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $command = $this->makeCommand([], [['command' => 'pw:static', 'on' => 'cron: 0 4 * * *']], $dispatcher);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    /**
     * @param Page[]                                    $pages
     * @param array<array{command: string, on: string}> $scheduledCommands
     */
    private function makeCommand(
        array $pages,
        array $scheduledCommands,
        ?BackgroundTaskDispatcherInterface $dispatcher = null,
    ): CronCommand {
        $pageRepo = self::createStub(PageRepository::class);
        $pageRepo->method('findNewlyPublishedSince')->willReturn($pages);

        return new CronCommand(
            $pageRepo,
            $dispatcher ?? self::createStub(BackgroundTaskDispatcherInterface::class),
            $this->varDir,
            $scheduledCommands,
        );
    }
}
