<?php

namespace Pushword\Flat\Tests\Command;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class FlatLockCommandTest extends KernelTestCase
{
    private const string HOST = 'lock-command.test';

    private Application $application;

    private FlatLockManager $lockManager;

    #[Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);

        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $this->lockManager = $lockManager;
        $this->lockManager->releaseLock(self::HOST);
    }

    protected function tearDown(): void
    {
        $this->lockManager->releaseLock(self::HOST);
        parent::tearDown();
    }

    public function testAcquiresLockOnFreeHost(): void
    {
        $commandTester = $this->runLockCommand(['host' => self::HOST, '--ttl' => 60]);

        self::assertStringContainsString('Lock acquired for host "'.self::HOST.'"', $commandTester->getDisplay());
        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertTrue($this->lockManager->isLocked(self::HOST));
    }

    public function testRefusesToOverrideAnExistingManualLock(): void
    {
        $this->lockManager->acquireLock(self::HOST, FlatLockManager::LOCK_TYPE_MANUAL, 60);

        $commandTester = $this->runLockCommand(['host' => self::HOST]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Already locked by manual', $output);
        self::assertStringContainsString('Cannot override existing manual lock', $output);
        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testOverridesAnAutoLock(): void
    {
        $this->lockManager->acquireLock(self::HOST, FlatLockManager::LOCK_TYPE_AUTO, 60);

        $commandTester = $this->runLockCommand(['host' => self::HOST, '--reason' => 'editorial']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Overriding auto-lock with manual lock', $output);
        self::assertStringContainsString('Lock acquired', $output);
        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        $lockInfo = $this->lockManager->getLockInfo(self::HOST);
        self::assertNotNull($lockInfo);
        self::assertSame('editorial', $lockInfo['reason']);
    }

    /** @param array<string, mixed> $args */
    private function runLockCommand(array $args): CommandTester
    {
        $commandTester = new CommandTester($this->application->find('pw:flat:lock'));
        $commandTester->execute($args);

        return $commandTester;
    }
}
