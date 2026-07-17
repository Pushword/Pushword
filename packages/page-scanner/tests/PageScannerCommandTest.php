<?php

namespace Pushword\PageScanner\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

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

    public function testADirectoryOnTheCachePathFailsFastInsteadOfLosingTheScan(): void
    {
        // Reported from a live project: something had left a directory where the
        // scan writes its cache. dumpFile() then threw "Is a directory" — after
        // minutes of rendering, with nothing said about what to do.
        $kernel = self::createKernel();
        $application = new Application($kernel);
        /** @var string $varDir */
        $varDir = self::getContainer()->getParameter('pw.var_dir');
        $blocked = $varDir.'/page-scan--localhost.dev';

        $filesystem = new Filesystem();
        $filesystem->remove($blocked);
        $filesystem->mkdir($blocked);

        try {
            $commandTester = new CommandTester($application->find('pw:page-scan'));
            $commandTester->execute(['host' => 'localhost.dev', '--format' => 'text', '--skip-external' => true]);

            self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
            self::assertStringContainsString('is a directory', $commandTester->getDisplay());
            // Fast: it never rendered a page.
            self::assertStringNotContainsString('Scanning', $commandTester->getDisplay());
        } finally {
            $filesystem->remove($blocked);
        }
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
