<?php

namespace Pushword\PageScanner\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PageScannerCommandTest extends KernelTestCase
{
    public function testPageScannerCommand()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:page-scanner:scan');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['localhost.dev']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertTrue(false !== strpos($output, 'done...'));
    }
}
