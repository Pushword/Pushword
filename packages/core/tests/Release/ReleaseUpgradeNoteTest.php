<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class ReleaseUpgradeNoteTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir().'/pushword-release-note-'.bin2hex(random_bytes(6));
        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $this->testRoot.'/.scripts',
            $this->testRoot.'/packages/docs/content/upgrade',
        ]);

        $repositoryRoot = \dirname(__DIR__, 4);
        $filesystem->copy(
            $repositoryRoot.'/.scripts/release-upgrade-note',
            $this->testRoot.'/.scripts/release-upgrade-note',
        );
        $filesystem->symlink($repositoryRoot.'/vendor', $this->testRoot.'/vendor');
        $filesystem->dumpFile(
            $this->testRoot.'/packages/docs/content/upgrade.md',
            "| Release | Packages | What changed |\n| --- | --- | --- |\n",
        );
        $filesystem->dumpFile(
            $this->testRoot.'/packages/docs/content/upgrade/next-release.md',
            <<<'MD'
                ---
                title: 'a setting changed'
                publishedAt: '2099-01-01 00:00'
                parentPage: upgrade
                run: cache:clear
                ---

                **Concerns:** `pushword/core`

                ## Update the setting

                Apply the new value.
                MD,
        );
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->testRoot);
    }

    /** @return iterable<string, array{string, string}> */
    public static function releaseVersions(): iterable
    {
        yield 'release candidate' => ['1.0.0-rc899', 'rc899'];
        yield 'stable release' => ['1.0.0', '1.0.0'];
        yield 'patch release' => ['1.0.1', '1.0.1'];
    }

    #[DataProvider('releaseVersions')]
    public function testPromotesTheDraftForReleaseCandidatesAndStableVersions(string $version, string $slug): void
    {
        $process = $this->runScript($version);

        self::assertSame(10, $process->getExitCode(), $process->getErrorOutput());

        $releasedPath = $this->testRoot.'/packages/docs/content/upgrade/'.$slug.'.md';
        self::assertFileExists($releasedPath);
        self::assertStringContainsString("h1: 'Upgrade to ".$version."'", (string) file_get_contents($releasedPath));

        $index = (string) file_get_contents($this->testRoot.'/packages/docs/content/upgrade.md');
        self::assertStringContainsString('| ['.$slug.'](/upgrade/'.$slug.') | `core` | a setting changed — run `cache:clear` |', $index);
    }

    /**
     * The draft is only parsed at release time, so a malformed one would abort the release
     * long after the commit that broke it.
     */
    public function testTheCommittedDraftIsReleasable(): void
    {
        $draft = (string) file_get_contents(\dirname(__DIR__, 4).'/packages/docs/content/upgrade/next-release.md');
        $this->writeDraft($draft);

        $process = $this->runScript('1.0.0-rc899');

        self::assertContains($process->getExitCode(), [0, 10], $process->getErrorOutput());
        self::assertLessThanOrEqual(
            1,
            preg_match_all('/^\*\*Concerns:\*\*/m', $draft),
            'Only the first **Concerns:** line is read: merge the packages of the others into it.',
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function unusableDrafts(): iterable
    {
        yield 'no frontmatter' => ["## A change\n", 'has no frontmatter'];
        yield 'sections without a title' => [
            "---\ntitle: ''\n---\n\n**Concerns:** `pushword/core`\n\n## A change\n",
            'has sections but no title',
        ];
        yield 'no Concerns line' => ["---\ntitle: 'a change'\n---\n\n## A change\n", 'has no **Concerns:** line'];
        yield 'package names without backticks' => [
            "---\ntitle: 'a change'\n---\n\n**Concerns:** pushword/core, pushword/flat\n\n## A change\n",
            'names no package',
        ];
    }

    #[DataProvider('unusableDrafts')]
    public function testRejectsAnUnusableDraftWithoutTouchingTheIndex(string $draft, string $expectedError): void
    {
        $this->writeDraft($draft);
        $indexPath = $this->testRoot.'/packages/docs/content/upgrade.md';
        $index = (string) file_get_contents($indexPath);

        $process = $this->runScript('1.0.0-rc899');

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString($expectedError, $process->getErrorOutput());
        self::assertStringEqualsFile($indexPath, $index);
        self::assertStringEqualsFile($this->testRoot.'/packages/docs/content/upgrade/next-release.md', $draft);
    }

    public function testTheResetScaffoldReleasesNothing(): void
    {
        $indexPath = $this->testRoot.'/packages/docs/content/upgrade.md';
        $index = (string) file_get_contents($indexPath);

        self::assertSame(0, $this->runScript('--reset')->getExitCode());
        $process = $this->runScript('1.0.0-rc899');

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertFileDoesNotExist($this->testRoot.'/packages/docs/content/upgrade/rc899.md');
        self::assertStringEqualsFile($indexPath, $index);
    }

    private function runScript(string $argument): Process
    {
        $process = new Process([\PHP_BINARY, $this->testRoot.'/.scripts/release-upgrade-note', $argument]);
        $process->run();

        return $process;
    }

    private function writeDraft(string $draft): void
    {
        new Filesystem()->dumpFile($this->testRoot.'/packages/docs/content/upgrade/next-release.md', $draft);
    }
}
