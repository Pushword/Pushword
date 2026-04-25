<?php

namespace Pushword\Flat\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\BackgroundProcessManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class FlatFileWatchCommandTest extends KernelTestCase
{
    public function testWatchRunsAndExitsWithMaxCycles(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $pidFile = $processManager->getPidFilePath('flat-sync');
        @unlink($pidFile);

        $command = $application->find('pw:flat:watch');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--max-cycles' => 2,
            '--interval' => '0.1',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Watching', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testWatchWithLiveReloadCreatesSignalFile(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $pidFile = $processManager->getPidFilePath('flat-sync');
        @unlink($pidFile);

        $signalFile = $kernel->getProjectDir().'/public/_flat-reload.txt';
        @unlink($signalFile);

        $command = $application->find('pw:flat:watch');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--max-cycles' => 1,
            '--interval' => '0.1',
            '--live-reload' => true,
        ]);

        self::assertFileExists($signalFile);

        @unlink($signalFile);
    }
}
