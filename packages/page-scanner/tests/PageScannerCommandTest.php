<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\PageScanner\Command\PageScannerCommand;
use Pushword\PageScanner\Scanner\PageScannerService;
use Pushword\PageScanner\Scanner\ParallelUrlChecker;
use Pushword\PageScanner\Service\LinkGraphStorage;
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

    public function testWithoutALimitTheScanRunsPastFiveHundredErrors(): void
    {
        // Regression: `--limit 0` read as "no limit" but meant 500, and the admin and
        // the API never pass `--limit`, so every scan they started stopped there.
        $kernel = self::bootKernel();
        $page = $this->persistPageWithBrokenLinks(501);

        try {
            $commandTester = new CommandTester(new Application($kernel)->find('pw:page-scan'));
            $commandTester->execute(['localhost.dev', '--format' => 'text', '--skip-external' => true]);
            $display = $commandTester->getDisplay();

            self::assertStringNotContainsString('stopping scan', $display);
            self::assertStringContainsString('done...', $display);
        } finally {
            $this->remove($page);
        }
    }

    public function testTheLimitStopsTheScan(): void
    {
        $kernel = self::bootKernel();
        $page = $this->persistPageWithBrokenLinks(2);

        try {
            $commandTester = new CommandTester(new Application($kernel)->find('pw:page-scan'));
            $commandTester->execute(['localhost.dev', '--limit' => 1, '--format' => 'text', '--skip-external' => true]);

            self::assertStringContainsString('Too many errors (>1), stopping scan...', $commandTester->getDisplay());
        } finally {
            $this->remove($page);
        }
    }

    public function testIgnoredErrorsDoNotCountTowardTheLimit(): void
    {
        self::bootKernel();
        $page = $this->persistPageWithBrokenLinks(2);

        try {
            // The same scan testTheLimitStopsTheScan() stops, with every finding ignored.
            $commandTester = new CommandTester(new Command(null, $this->commandIgnoring(['*'])));
            $commandTester->execute(['host' => 'localhost.dev', '--limit' => 1, '--format' => 'text', '--skip-external' => true]);
            $display = $commandTester->getDisplay();

            self::assertStringNotContainsString('stopping scan', $display);
            self::assertStringContainsString('done...', $display);
            // A page whose every finding is ignored prints nothing, not even its route.
            self::assertStringNotContainsString('localhost.dev/limit-probe', $display);
        } finally {
            $this->remove($page);
        }
    }

    public function testAnIgnoredErrorIsNotPrintedNextToAVisibleOne(): void
    {
        self::bootKernel();
        $page = $this->persistPageWithBrokenLinks(2);

        try {
            $commandTester = new CommandTester(new Command(null, $this->commandIgnoring(['localhost.dev/limit-probe: */limit-probe-missing-1 *'])));
            $commandTester->execute(['host' => 'localhost.dev', '--format' => 'text', '--skip-external' => true]);
            $display = $commandTester->getDisplay();

            self::assertStringContainsString("\nlocalhost.dev/limit-probe\n", $display);
            self::assertStringContainsString('/limit-probe-missing-2', $display);
            self::assertStringNotContainsString('/limit-probe-missing-1', $display);
        } finally {
            $this->remove($page);
        }
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

        // Each finding names itself: the code is what `errors_to_ignore` takes, so an
        // agent can silence one without guessing its identity from the wording.
        self::assertIsArray($decoded['issues']);
        foreach ($decoded['issues'] as $issue) {
            self::assertIsArray($issue);
            self::assertIsArray($issue['errors']);
            foreach ($issue['errors'] as $error) {
                self::assertIsArray($error);
                self::assertArrayHasKey('code', $error);
                self::assertArrayHasKey('message', $error);
            }
        }
    }

    /**
     * @param string[] $errorsToIgnore
     */
    private function commandIgnoring(array $errorsToIgnore): PageScannerCommand
    {
        $container = self::getContainer();
        /** @var string $varDir */
        $varDir = $container->getParameter('pw.var_dir');

        return new PageScannerCommand(
            $container->get(PageScannerService::class),
            new Filesystem(),
            $container->get(PageRepository::class),
            $container->get(ParallelUrlChecker::class),
            $container->get(BackgroundProcessManager::class),
            $container->get(ProcessOutputStorage::class),
            $container->get(LinkGraphStorage::class),
            $errorsToIgnore,
            $varDir,
        );
    }

    /** A page whose every link is a distinct `link-not-found` finding. */
    private function persistPageWithBrokenLinks(int $count): Page
    {
        $page = new Page();
        $page->h1 = 'Limit probe';
        $page->slug = 'limit-probe';
        $page->locale = 'en';
        $page->host = 'localhost.dev';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->mainContent = implode(' ', array_map(static fn (int $i): string => '[link](/limit-probe-missing-'.$i.')', range(1, $count)));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($page);
        $entityManager->flush();

        return $page;
    }

    private function remove(Page $page): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($page);
        $entityManager->flush();
    }
}
