<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class PdfOptimizerCommandTest extends KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists('test.pdf');
    }

    public function testExecuteWithNoPdfs(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:pdf:optimize');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->getDisplay();
        // Either "No PDF files" or shows progress (if PDFs exist in DB)
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testExecuteWithSpecificPdf(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:pdf:optimize');
        $commandTester = new CommandTester($command);
        // A name in no fixture, so "not found" is true by construction. 'test.pdf' was not:
        // the command resolves the name against the database, and test.pdf is both a row in
        // docs/content/media.csv and a file the bootstrap mirrors into every worker's media
        // dir — so any class importing that content (FlatCommandTest, say) creates the Media
        // row, and this assertion came down to what else ran in the ParaTest worker first.
        $commandTester->execute(['media' => 'absent-from-the-database.pdf']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('PDF not found', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testCommandDescription(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:pdf:optimize');

        self::assertStringContainsString('Optimize PDF files', $command->getDescription());
    }
}
