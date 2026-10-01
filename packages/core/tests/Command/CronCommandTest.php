<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Command;

use DateTime;
use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Command\CronCommand;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class CronCommandTest extends TestCase
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
        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $command = $this->makeCommand([], [['command' => 'pw:static {host} -i', 'on' => 'publish']], $dispatcher);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileExists($this->varDir.'/pw-cron-last-run');
    }

    public function testNoNewlyPublishedPagesDispatchesNothing(): void
    {
        // Initialize timestamp so it's not first run
        touch($this->varDir.'/pw-cron-last-run', new DateTime('-1 hour')->getTimestamp());

        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $command = $this->makeCommand([], [['command' => 'pw:static {host} -i', 'on' => 'publish']], $dispatcher);
        $result = $command(new NullOutput());

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testHostPlaceholderDispatchesOncePerSite(): void
    {
        touch($this->varDir.'/pw-cron-last-run', new DateTime('-1 hour')->getTimestamp());

        $pages = [];
        foreach (['example.tld', 'www.example.tld', '', 'other.tld', 'other.tld'] as $host) {
            $page = new Page();
            $page->host = $host;
            $pages[] = $page;
        }

        $calls = [];
        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function (string $processType, array $commandParts, string $commandPattern) use (&$calls): void {
                $calls[] = [$processType, $commandParts, $commandPattern];
            },
        );

        $command = $this->makeCommand($pages, [['command' => 'pw:static {host} -i', 'on' => 'publish']], $dispatcher);

        self::assertSame(Command::SUCCESS, $command(new NullOutput()));
        self::assertSame([
            ['cron-publish--pw:static--example.tld', ['php', 'bin/console', 'pw:static', 'example.tld', '-i'], 'pw:static'],
            ['cron-publish--pw:static--other.tld', ['php', 'bin/console', 'pw:static', 'other.tld', '-i'], 'pw:static'],
        ], $calls);
    }

    public function testHostPlaceholderPreservesQuotedArguments(): void
    {
        touch($this->varDir.'/pw-cron-last-run', new DateTime('-1 hour')->getTimestamp());

        $page = new Page();
        $page->host = 'other.tld';

        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(
            'cron-publish--app:publish--other.tld',
            ['php', 'bin/console', 'app:publish', '--host=other.tld', '--message=A new page'],
            'app:publish',
        );

        $command = $this->makeCommand([$page], [
            ['command' => 'app:publish --host="{host}" --message="A new page"', 'on' => 'publish'],
        ], $dispatcher);

        self::assertSame(Command::SUCCESS, $command(new NullOutput()));
    }

    public function testExplicitHostIsPreserved(): void
    {
        touch($this->varDir.'/pw-cron-last-run', new DateTime('-1 hour')->getTimestamp());

        $page = new Page();
        $page->host = 'example.tld';

        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(
            'cron-publish',
            ['php', 'bin/console', 'pw:static', 'other.tld', '-i'],
            'pw:static other.tld -i',
        );

        $command = $this->makeCommand([$page], [['command' => 'pw:static other.tld -i', 'on' => 'publish']], $dispatcher);

        self::assertSame(Command::SUCCESS, $command(new NullOutput()));
    }

    public function testMultipleHostCommandsUseDistinctProcessTypes(): void
    {
        touch($this->varDir.'/pw-cron-last-run', new DateTime('-1 hour')->getTimestamp());

        $page = new Page();
        $page->host = 'example.tld';

        $calls = [];
        $dispatcher = $this->createMock(BackgroundTaskDispatcherInterface::class);
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function (string $processType, array $commandParts, string $commandPattern) use (&$calls): void {
                $calls[] = [$processType, $commandParts, $commandPattern];
            },
        );

        $command = $this->makeCommand([$page], [
            ['command' => 'pw:static {host}', 'on' => 'publish'],
            ['command' => 'app:sitemap {host}', 'on' => 'publish'],
            ['command' => 'pw:static', 'on' => 'cron: 0 4 * * *'],
        ], $dispatcher);

        self::assertSame(Command::SUCCESS, $command(new NullOutput()));
        self::assertSame([
            ['cron-publish--pw:static--example.tld', ['php', 'bin/console', 'pw:static', 'example.tld'], 'pw:static'],
            ['cron-publish--app:sitemap--example.tld', ['php', 'bin/console', 'app:sitemap', 'example.tld'], 'app:sitemap'],
        ], $calls);
    }

    public function testNewlyPublishedPageDispatchesCommands(): void
    {
        // Initialize timestamp so it's not first run
        touch($this->varDir.'/pw-cron-last-run', new DateTime('-1 hour')->getTimestamp());

        $page = new Page();
        $page->publishedAt = new DateTime('-30 minutes');

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
            new SiteRegistry(
                [
                    'example.tld' => ['hosts' => ['example.tld', 'www.example.tld'], 'locale' => 'en'],
                    'other.tld' => ['hosts' => ['other.tld'], 'locale' => 'en'],
                ],
                new TemplateResolver(new Environment(new ArrayLoader()), new ArrayAdapter()),
                new ParameterBag(),
            ),
        );
    }
}
