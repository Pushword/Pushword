<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
class PdfOptimizerCommandTest extends KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists('test.pdf');
    }

    public function testExecuteWithNoPdfs(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:pdf:optimize');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        // Either "No PDF files" or shows progress (if PDFs exist in DB)
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithSpecificPdf(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:pdf:optimize');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['media' => 'test.pdf']);

        $output = $commandTester->getDisplay();
        // PDF not in database, should show "PDF not found"
        self::assertStringContainsString('PDF not found', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testCommandDescription(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:pdf:optimize');

        self::assertStringContainsString('Optimize PDF files', $command->getDescription());
    }
}
