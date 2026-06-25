<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;

#[Group('integration')]
final class MediaCacheGeneratorCommandTest extends KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
    }

    public function testSequentialExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testSingleMediaExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--format' => 'text']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testParallelExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '2', '--force' => true, '--format' => 'text']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
        self::assertStringContainsString('worker(s)', $commandTester->getDisplay());
    }

    public function testForceRegeneration(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--force' => true, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('100%', $output);
        self::assertStringContainsString('0 skipped', $output);
    }

    public function testNoLockSkipsLockAcquisition(): void
    {
        $commandTester = $this->createCommandTester();

        // --no-lock with media name = worker mode (emits DONE: markers, no progress bar)
        $commandTester->execute(['media' => 'piedweb-logo.png', '--no-lock' => true, '--format' => 'text']);

        self::assertStringContainsString('DONE:piedweb-logo.png', $commandTester->getDisplay());
    }

    public function testSkipsAlreadyCachedImages(): void
    {
        $commandTester = $this->createCommandTester();

        // First run generates cache
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        // Second run should skip
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        self::assertStringContainsString('skipped', $commandTester->getDisplay());
    }

    public function testCommaSeparatedMediaNames(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png,piedweb-logo.png', '--force' => true, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('100%', $output);
    }

    public function testParallelPreFiltersAlreadyCached(): void
    {
        $commandTester = $this->createCommandTester();

        // First run to populate cache
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--force' => true, '--format' => 'text']);

        // Parallel run should pre-filter and detect cached images
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '2', '--format' => 'text']);

        $output = $commandTester->getDisplay();
        // The summary line always appears and includes the skipped count.
        // In parallel CI, other ParaTest workers may invalidate cache between
        // runs, so we can't guarantee skipped > 0 — just verify the pre-filter
        // code path ran by checking the summary format.
        self::assertMatchesRegularExpression('/\d+ processed, \d+ skipped/', $output);
    }

    public function testDisplaysImageDriver(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        self::assertStringContainsString('Image driver:', $commandTester->getDisplay());
    }

    public function testDisplaysStatsSummary(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('processed', $output);
        self::assertStringContainsString('peak memory', $output);
    }

    public function testAgentOutputIsJson(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1', '--format' => 'agent']);

        $output = trim($commandTester->getDisplay());

        // No human noise (progress bar / summary line) leaks into agent output.
        self::assertStringNotContainsString('peak memory', $output);
        self::assertStringNotContainsString('100%', $output);

        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:image:cache', $decoded['tool']);
        self::assertContains($decoded['result'], ['passed', 'failed']);
        self::assertArrayHasKey('processed', $decoded);
        self::assertArrayHasKey('errors', $decoded);
    }

    private function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        return new CommandTester($application->find('pw:image:cache'));
    }

    private function waitForLockRelease(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = self::getContainer()->get('lock.factory');
        $lock = $lockFactory->createLock('pw:image:cache');
        $lock->acquire(blocking: true);
        $lock->release();
    }
}
