<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
class FlatCommandTest extends KernelTestCase
{
    public function testSync(): void
    {
        $kernel = static::createKernel();
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
            '--no-backup' => true,
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
        ]);

        $exportOutput = $commandTester->getDisplay();
        self::assertStringContainsString('Sync completed', $exportOutput);
        self::assertStringContainsString('export mode', $exportOutput);
    }
}
