<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FlatCommandTest extends KernelTestCase
{
    public function testSync(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        // Test import mode
        $command = $application->find('pw:flat:sync');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'import',
            '--skip-id' => true,
            '--no-backup' => true,
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Sync completed', $output);

        // Test export mode
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            '--mode' => 'export',
            '--skip-id' => true,
            '--force' => true,
        ]);

        $exportOutput = $commandTester->getDisplay();
        self::assertStringContainsString('Sync completed', $exportOutput);
    }
}
