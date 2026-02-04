<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;

#[Group('integration')]
class MediaCacheGeneratorCommandTest extends KernelTestCase
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
        $commandTester->execute(['--parallel' => '1']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testSingleMediaExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testParallelExecution(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '2']);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
        self::assertStringContainsString('parallel worker(s)', $commandTester->getDisplay());
    }

    public function testForceRegeneration(): void
    {
        $commandTester = $this->createCommandTester();

        $this->waitForLockRelease();
        $commandTester->execute(['media' => 'piedweb-logo.png', '--force' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('100%', $output);
        self::assertStringNotContainsString('skipped', $output);
    }

    public function testNoLockSkipsLockAcquisition(): void
    {
        $commandTester = $this->createCommandTester();

        $commandTester->execute(['media' => 'piedweb-logo.png', '--no-lock' => true]);

        self::assertStringContainsString('100%', $commandTester->getDisplay());
    }

    public function testSkipsAlreadyCachedImages(): void
    {
        $commandTester = $this->createCommandTester();

        // First run generates cache
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1']);

        // Second run should skip
        $this->waitForLockRelease();
        $commandTester->execute(['--parallel' => '1']);

        self::assertStringContainsString('skipped', $commandTester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $kernel = static::bootKernel();
        $application = new Application($kernel);

        return new CommandTester($application->find('pw:image:cache'));
    }

    private function waitForLockRelease(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get('lock.factory');
        $lock = $lockFactory->createLock('pw:image:cache');
        $lock->acquire(blocking: true);
        $lock->release();
    }
}
