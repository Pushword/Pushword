<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Command\SimpleBackupCommand;
use Pushword\Core\Service\SqliteBackupManager;

use function Safe\file_get_contents;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The command resolves var/app.db relative to the working directory, so each test
 * runs from a throwaway directory rather than the repository root.
 */
final class SimpleBackupCommandTest extends TestCase
{
    private string $workingDir;

    private string $previousWorkingDir;

    private SimpleBackupCommand $command;

    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousWorkingDir = (string) getcwd();
        $this->workingDir = sys_get_temp_dir().'/pw-backup-test-'.uniqid();

        $filesystem = new Filesystem();
        $filesystem->mkdir($this->workingDir.'/var');
        $filesystem->dumpFile($this->workingDir.'/var/app.db', 'current');

        chdir($this->workingDir);

        $this->command = new SimpleBackupCommand(new SqliteBackupManager($filesystem));
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);
        new Filesystem()->remove($this->workingDir);

        parent::tearDown();
    }

    public function testCreateBackupCopiesTheDatabase(): void
    {
        $this->command->createBackup($this->output);

        self::assertStringContainsString('Backup created: var/app.db~', $this->output->fetch());
        self::assertCount(1, $this->backupFiles());
    }

    public function testCreateAutomaticallyKeepsTheTenMostRecentBackups(): void
    {
        foreach (range(0, 9) as $index) {
            $stamp = \sprintf('200001%02d000000', $index + 1);
            new Filesystem()->dumpFile($this->workingDir.'/var/app.db~'.$stamp, $stamp);
        }

        $this->command->createBackup();

        self::assertCount(SqliteBackupManager::AUTOMATIC_RETENTION, $this->backupFiles());
        self::assertFileDoesNotExist($this->workingDir.'/var/app.db~20000101000000');
    }

    public function testServerDatabaseIsRejectedBeforeAFileOperation(): void
    {
        $command = new SimpleBackupCommand(new SqliteBackupManager(
            new Filesystem(),
            databaseUrl: 'postgresql://localhost/pushword',
        ));

        self::assertSame(Command::FAILURE, $command($this->output));
        self::assertStringContainsString('supports SQLite databases only', $this->output->fetch());
    }

    public function testRestoreLastBackupOverwritesTheDatabase(): void
    {
        new Filesystem()->dumpFile($this->workingDir.'/var/app.db~20200101000000', 'older');
        new Filesystem()->dumpFile($this->workingDir.'/var/app.db~20300101000000', 'newer');

        $this->command->restoreLastBackup($this->output);

        self::assertStringContainsString('Restored from: var/app.db~20300101000000', $this->output->fetch());
        self::assertSame('newer', file_get_contents($this->workingDir.'/var/app.db'));
    }

    public function testRestoreReportsWhenNoBackupExists(): void
    {
        $this->command->restoreLastBackup($this->output);

        self::assertStringContainsString('No backup files found', $this->output->fetch());
        self::assertSame('current', file_get_contents($this->workingDir.'/var/app.db'));
    }

    public function testCleanKeepsTheMostRecentBackupOnly(): void
    {
        foreach (['20200101000000', '20250101000000', '20300101000000'] as $stamp) {
            new Filesystem()->dumpFile($this->workingDir.'/var/app.db~'.$stamp, $stamp);
        }

        $this->command->cleanBackups($this->output);

        $display = $this->output->fetch();
        self::assertStringContainsString('Removed: var/app.db~20250101000000', $display);
        self::assertStringContainsString('Removed: var/app.db~20200101000000', $display);
        self::assertStringContainsString('Cleaned 2 old backup(s), kept 1 most recent', $display);
        self::assertSame(['var/app.db~20300101000000'], $this->backupFiles());
    }

    public function testCleanReportsWhenThereIsNothingToRemove(): void
    {
        new Filesystem()->dumpFile($this->workingDir.'/var/app.db~20300101000000', 'only');

        $this->command->cleanBackups($this->output);

        self::assertStringContainsString('No old backups to remove (keeping 1 most recent)', $this->output->fetch());
    }

    public function testCleanReportsWhenThereIsNoBackupAtAll(): void
    {
        $this->command->cleanBackups($this->output);

        self::assertStringContainsString('No backup files to clean', $this->output->fetch());
    }

    /** @return string[] */
    private function backupFiles(): array
    {
        $files = glob('var/app.db~*');

        return false === $files ? [] : $files;
    }
}
