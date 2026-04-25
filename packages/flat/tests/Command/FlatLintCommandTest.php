<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Command;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class FlatLintCommandTest extends KernelTestCase
{
    private Application $application;

    private string $contentDir;

    private Filesystem $filesystem;

    /** @var string[] */
    private array $createdFiles = [];

    #[Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $this->filesystem = new Filesystem();

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function testLintDetectsInvalidYaml(): void
    {
        $path = $this->contentDir.'/lint-test-invalid.md';
        $this->filesystem->dumpFile($path, "---\nh1: [invalid yaml: {broken\n---\n\nContent");
        $this->createdFiles[] = $path;

        $command = $this->application->find('pw:flat:lint');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('lint-test-invalid.md', $tester->getDisplay());
        self::assertStringContainsString('YAML errors', $tester->getDisplay());
    }

    public function testLintDetectsUnescapedQuote(): void
    {
        $path = $this->contentDir.'/lint-test-quote.md';
        $this->filesystem->dumpFile($path, "---\ntitle: 'La Baltique : îles de Rügen et d'Usedom | Grand Angle'\n---\n\nContent");
        $this->createdFiles[] = $path;

        $command = $this->application->find('pw:flat:lint');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('lint-test-quote.md', $tester->getDisplay());
    }

    public function testLintPassesWithValidFiles(): void
    {
        $command = $this->application->find('pw:flat:lint');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('valid YAML front matter', $tester->getDisplay());
    }

    public function testSyncShowsYamlErrorWarning(): void
    {
        $path = $this->contentDir.'/lint-sync-warning.md';
        $this->filesystem->dumpFile($path, "---\nh1: [broken: {yaml\n---\n\nContent");
        touch($path, time() + 100);
        $this->createdFiles[] = $path;

        $command = $this->application->find('pw:flat:sync');
        $tester = new CommandTester($command);
        $tester->execute(['host' => 'localhost.dev', '--mode' => 'import']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('YAML front matter errors', $tester->getDisplay());
        self::assertStringContainsString('pw:flat:lint', $tester->getDisplay());
    }
}
