<?php

namespace Pushword\Flat\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends KernelTestCase
{
    public function testImport()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:flat:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertTrue(false !== strpos($output, 'ended'));

        $exportDir = $kernel->getCacheDir().'/test-exporter';

        $command = $application->find('pushword:flat:export');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'host' => 'pushword.piedweb.com',
            'exportDir' => $exportDir,
        ]);

        $this->assertFileExists($exportDir.'/homepage.md');
        $this->assertFileExists($exportDir.'/installation.md');
    }
}
