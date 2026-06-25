<?php

namespace Pushword\PageScanner\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class PageScannerCommandTest extends KernelTestCase
{
    public function testPageScannerCommand(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:page-scan');
        $commandTester = new CommandTester($command);
        // Force text so the assertion is stable even when run inside an AI agent.
        $commandTester->execute(['localhost.dev', '--format' => 'text']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('done...', $output);
    }

    public function testPageScannerCommandWithLimit(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:page-scan');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['localhost.dev', '--limit' => 1, '--format' => 'text']);

        $output = $commandTester->getDisplay();
        self::assertTrue(str_contains($output, 'Too many errors (>1), stopping scan...') || str_contains($output, 'done...'));
    }

    public function testPageScannerCommandAgentOutputIsJson(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:page-scan');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['localhost.dev', '--format' => 'agent', '--skip-external' => true]);

        $output = trim($commandTester->getDisplay());

        // No human noise leaks into agent output.
        self::assertStringNotContainsString('done...', $output);
        self::assertStringNotContainsString('Scanning', $output);
        self::assertStringNotContainsString('PID:', $output);

        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:page-scan', $decoded['tool']);
        self::assertContains($decoded['result'], ['passed', 'failed']);
        self::assertArrayHasKey('pages_scanned', $decoded);
        self::assertArrayHasKey('errors', $decoded);
        self::assertArrayHasKey('issues', $decoded);
        self::assertArrayHasKey('duration_ms', $decoded);
    }
}
