<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\TailwindGenerator;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class TailwindGeneratorTest extends KernelTestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/pw-tailwind-'.uniqid()." project's \$dir";
        $fs = new Filesystem();
        $fs->mkdir([$this->projectDir.'/assets', $this->projectDir.'/bin']);
        $fs->dumpFile(
            $this->projectDir.'/bin/npm',
            "#!/bin/sh\nexec ".escapeshellarg(\PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixtures/tailwind-build.php')."\n",
        );
        $fs->chmod($this->projectDir.'/bin/npm', 0o755);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        new Filesystem()->remove($this->projectDir);
    }

    public function testConsoleTerminationFlushesBatchThroughRegisteredListener(): void
    {
        self::bootKernel();
        $generator = $this->generator();
        self::getContainer()->set(TailwindGenerator::class, $generator);
        $dispatcher = self::getContainer()->get('event_dispatcher');

        $generator->run($this->page(1));
        $generator->run($this->page(2));

        $dispatcher->dispatch(
            new ConsoleTerminateEvent(new Command('pw:flat:sync'), new ArrayInput([]), new NullOutput(), 0),
            ConsoleEvents::TERMINATE,
        );

        $builds = $this->builds();
        self::assertCount(1, $builds);
        self::assertCount(2, $builds[0]);
    }

    public function testBulkSavesBuildOnceAfterAllContentIsCached(): void
    {
        $generator = $this->generator();
        for ($id = 1; $id <= 20; ++$id) {
            $generator->run($this->page($id, 'content-'.$id));
        }

        $generator->run($this->page(1, 'updated-content'));
        self::assertFileDoesNotExist($this->projectDir.'/builds');
        self::assertSame('updated-content', file_get_contents($this->projectDir.'/var/TailwindGeneratorCache/1'));

        $generator->flush();
        $generator->flush();

        $builds = $this->builds();
        self::assertCount(1, $builds);
        self::assertCount(20, $builds[0]);
        self::assertSame('updated-content', $builds[0][1]);
        self::assertSame('content-20', $builds[0][20]);
        self::assertSame("Build output\nBuild diagnostic\n", file_get_contents($this->projectDir.'/var/log/lastTailwindGeneration'));
    }

    #[DataProvider('inactiveGeneratorProvider')]
    public function testInactiveGeneratorDoesNotCacheOrBuild(bool $active, string $environment, bool $assetsExist): void
    {
        if (! $assetsExist) {
            new Filesystem()->remove($this->projectDir.'/assets');
        }

        $generator = $this->generator($active, $environment);
        $generator->run($this->page(1));
        $generator->flush();

        self::assertDirectoryDoesNotExist($this->projectDir.'/var');
        self::assertFileDoesNotExist($this->projectDir.'/builds');
    }

    /** @return iterable<string, array{bool, string, bool}> */
    public static function inactiveGeneratorProvider(): iterable
    {
        yield 'disabled' => [false, 'prod', true];
        yield 'dev' => [true, 'dev', true];
        yield 'test' => [true, 'test', true];
        yield 'no assets' => [true, 'prod', false];
    }

    public function testResetDoesNotReplayPendingBuildInNextRequest(): void
    {
        $generator = $this->generator();
        $generator->run($this->page(1));
        $generator->reset();
        $generator->flush();
        self::assertFileDoesNotExist($this->projectDir.'/builds');

        $generator->run($this->page(2));
        $generator->flush();
        self::assertCount(1, $this->builds());
    }

    public function testFailedBuildReleasesLockForNextSave(): void
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->projectDir.'/fail-build', '');

        $generator = $this->generator();
        $generator->run($this->page(1));
        $generator->flush();

        $fs->remove($this->projectDir.'/fail-build');
        $generator->run($this->page(2));
        $generator->flush();
        self::assertCount(2, $this->builds());
    }

    public function testBuildExceptionReleasesLockForNextSave(): void
    {
        $generator = $this->generator();
        $generator->run($this->page(1));

        $fs = new Filesystem();
        $fs->remove($this->projectDir.'/assets');

        try {
            $generator->flush();
            self::fail('A missing build directory must fail to launch.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('cwd', $runtimeException->getMessage());
        }

        $fs->mkdir($this->projectDir.'/assets');
        $generator->run($this->page(2));
        $generator->flush();
        self::assertCount(1, $this->builds());
    }

    public function testConcurrentSavesSerializeAndCoalesceTheNextBuild(): void
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->projectDir.'/block-build', '');

        $first = $this->startFlush(1);
        $second = null;
        $third = null;

        try {
            $this->waitForFile($this->projectDir.'/build-started');
            $second = $this->startFlush(2);
            $this->waitForFile($this->projectDir.'/var/tailwind-build.pending');
            // dumpFile replaces the inode: wait until the third caller has queued its build.
            $pendingInode = fileinode($this->projectDir.'/var/tailwind-build.pending');
            self::assertIsInt($pendingInode);
            $third = $this->startFlush(3);
            $this->waitForFile($this->projectDir.'/var/tailwind-build.pending', $pendingInode);

            self::assertCount(1, $this->builds());
            self::assertTrue($second->isRunning());
            self::assertTrue($third->isRunning());

            $fs->dumpFile($this->projectDir.'/release-build', '');
            foreach ([$first, $second, $third] as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
            }

            $builds = $this->builds();
            self::assertCount(2, $builds);
            self::assertCount(1, $builds[0]);
            self::assertSame('page-2', $builds[1][2]);
            self::assertSame('page-3', $builds[1][3]);
            self::assertFileDoesNotExist($this->projectDir.'/overlap');
            self::assertFileDoesNotExist($this->projectDir.'/var/tailwind-build.pending');
        } finally {
            $fs->dumpFile($this->projectDir.'/release-build', '');
            foreach ([$first, $second, $third] as $process) {
                $process?->stop();
            }
        }
    }

    private function generator(bool $active = true, string $environment = 'prod'): TailwindGenerator
    {
        $kernel = self::createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn($environment);

        return new TailwindGenerator($active, $this->projectDir, $this->projectDir.'/bin', $kernel);
    }

    private function page(int $id, string $content = 'content'): Page
    {
        $page = new Page();
        new ReflectionProperty(Page::class, 'id')->setValue($page, $id);
        $page->mainContent = $content;

        return $page;
    }

    /** @return list<array<int, string>> */
    private function builds(): array
    {
        $lines = file($this->projectDir.'/builds', \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);

        return array_map(static function (string $line): array {
            /** @var array<int, string> */
            return json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        }, $lines);
    }

    private function startFlush(int $id): Process
    {
        $process = new Process([\PHP_BINARY, __DIR__.'/fixtures/tailwind-flush.php', $this->projectDir, (string) $id], timeout: 15);
        $process->start();

        return $process;
    }

    private function waitForFile(string $path, ?int $previousInode = null): void
    {
        $deadline = microtime(true) + 5;
        do {
            clearstatcache(true, $path);
            if (file_exists($path) && (null === $previousInode || fileinode($path) !== $previousInode)) {
                return;
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('Timed out waiting for '.$path);
    }
}
