<?php

namespace Pushword\Flat\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class FlatCommandTest extends KernelTestCase
{
    public function testBackupOnlyRunsOnSqlite(): void
    {
        $application = new Application(self::createKernel());
        $commandTester = new CommandTester($application->find('pw:flat:sync'));
        $status = $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'import',
            '--backup' => true,
            '--format' => 'text',
        ]);

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame(Command::SUCCESS, $status);

            return;
        }

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('supports SQLite databases only', $commandTester->getDisplay());
    }

    public function testSync(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        // Clean up any PID file left by parallel tests
        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $pidFile = $processManager->getPidFilePath('flat-sync');
        @unlink($pidFile);

        // Test import mode
        $command = $application->find('pw:flat:sync');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'import',
            '--format' => 'text',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Sync completed', $output);
        self::assertStringContainsString('import mode', $output);
        self::assertStringNotContainsString('export mode', $output);

        // Test export mode
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'export',
            '--force' => true,
            '--format' => 'text',
        ]);

        $exportOutput = $commandTester->getDisplay();
        self::assertStringContainsString('Sync completed', $exportOutput);
        self::assertStringContainsString('export mode', $exportOutput);
    }

    public function testPageOptionFiltersSync(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $pidFile = $processManager->getPidFilePath('flat-sync');
        @unlink($pidFile);

        $command = $application->find('pw:flat:sync');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'import',
            '--page' => ['homepage'],
            '--format' => 'text',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Sync completed', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testSyncAgentOutputIsJson(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        @unlink($processManager->getPidFilePath('flat-sync'));

        $command = $application->find('pw:flat:sync');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'import',
            '--format' => 'agent',
        ]);

        $output = trim($commandTester->getDisplay());
        self::assertStringNotContainsString('Sync completed', $output);
        self::assertStringNotContainsString('PID:', $output);

        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:flat:sync', $decoded['tool']);
        self::assertArrayHasKey('imported', $decoded);
        self::assertArrayHasKey('exported', $decoded);
        self::assertArrayHasKey('duration_ms', $decoded);
    }
}
