<?php

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
        $process = new Process([\PHP_BINARY, $this->testRoot.'/.scripts/release-upgrade-note', $version]);
        $process->run();

        self::assertSame(10, $process->getExitCode(), $process->getErrorOutput());

        $releasedPath = $this->testRoot.'/packages/docs/content/upgrade/'.$slug.'.md';
        self::assertFileExists($releasedPath);
        self::assertStringContainsString("h1: 'Upgrade to ".$version."'", (string) file_get_contents($releasedPath));

        $index = (string) file_get_contents($this->testRoot.'/packages/docs/content/upgrade.md');
        self::assertStringContainsString('| ['.$slug.'](/upgrade/'.$slug.') | `core` | a setting changed — run `cache:clear` |', $index);
    }
}
