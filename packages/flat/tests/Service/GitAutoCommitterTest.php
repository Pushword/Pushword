<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Service\GitAutoCommitter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class GitAutoCommitterTest extends KernelTestCase
{
    private string $tempDir;

    private Filesystem $fs;

    #[Override]
    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/git-auto-commit-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->fs->remove($this->tempDir);
        }

        parent::tearDown();
    }

    public function testSkipsWhenDisabled(): void
    {
        $committer = $this->createCommitter(false);

        self::assertFalse($committer->isEnabled());
        self::assertFalse($committer->commit($this->tempDir, 'test'));
    }

    public function testSkipsWhenNoGitRepo(): void
    {
        $committer = $this->createCommitter(true);

        self::assertFalse($committer->commit($this->tempDir, 'test'));
    }

    public function testSkipsWhenNoChanges(): void
    {
        $this->initGitRepo();

        $committer = $this->createCommitter(true);

        self::assertFalse($committer->commit($this->tempDir, 'test'));
    }

    public function testCommitsWhenChangesExist(): void
    {
        $this->initGitRepo();

        // Create a new file
        file_put_contents($this->tempDir.'/new-page.md', '# Hello');

        $committer = $this->createCommitter(true);
        $result = $committer->commit($this->tempDir, 'Auto-commit: test');

        self::assertTrue($result);

        // Verify commit was made
        $logOutput = shell_exec('git -C '.escapeshellarg($this->tempDir).' log --oneline -1');
        self::assertStringContainsString('Auto-commit: test', (string) $logOutput);
    }

    public function testDetectsGitRepoInParentDir(): void
    {
        $this->initGitRepo();

        $subDir = $this->tempDir.'/subdir';
        mkdir($subDir, 0755, true);
        file_put_contents($subDir.'/test.md', '# Test');

        $committer = $this->createCommitter(true);
        $result = $committer->commit($subDir, 'Auto-commit: subdir test');

        self::assertTrue($result);
    }

    private function createCommitter(bool $enabled): GitAutoCommitter
    {
        self::bootKernel();
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);

        return new GitAutoCommitter($enabled, $contentDirFinder, new NullLogger());
    }

    private function initGitRepo(): void
    {
        exec('git -C '.escapeshellarg($this->tempDir).' init 2>&1');
        exec('git -C '.escapeshellarg($this->tempDir).' config user.email "test@test.com" 2>&1');
        exec('git -C '.escapeshellarg($this->tempDir).' config user.name "Test" 2>&1');
        // Create initial commit so the repo isn't empty
        file_put_contents($this->tempDir.'/.gitkeep', '');
        exec('git -C '.escapeshellarg($this->tempDir).' add -A 2>&1');
        exec('git -C '.escapeshellarg($this->tempDir).' commit -m "init" 2>&1');
    }
}
