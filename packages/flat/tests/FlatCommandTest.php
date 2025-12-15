<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FlatCommandTest extends KernelTestCase
{
    public function testImport(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:flat:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['host' => 'pushword.piedweb.com']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Imported', $output);

        $exportDir = $kernel->getCacheDir().'/test-exporter';

        $command = $application->find('pw:flat:export');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            'exportDir' => $exportDir,
        ]);

        $exportOutput = $commandTester->getDisplay();
        self::assertStringContainsString('Export completed', $exportOutput);
        self::assertStringContainsString('Results stored in '.$exportDir, $exportOutput);
        self::assertFileExists($exportDir.'/homepage.md');
        self::assertFileExists($exportDir.'/installation.md');
    }
}
