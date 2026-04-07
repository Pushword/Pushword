<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
class PageScannerCommandTest extends KernelTestCase
{
    public function testPageScannerCommand(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:page-scan');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['localhost.dev']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertTrue(str_contains($output, 'done...'));
    }

    public function testPageScannerCommandWithLimit(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:page-scan');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['localhost.dev', '--limit' => 1]);

        $output = $commandTester->getDisplay();
        self::assertTrue(str_contains($output, 'Too many errors (>1), stopping scan...') || str_contains($output, 'done...'));
    }
}
