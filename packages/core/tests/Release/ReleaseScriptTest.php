<?php

namespace Pushword\Core\Tests\Release;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class ReleaseScriptTest extends TestCase
{
    private string $remoteRoot;

    private string $testRoot;

    protected function setUp(): void
    {
        $temporaryRoot = sys_get_temp_dir().'/pushword-release-'.bin2hex(random_bytes(6));
        $this->testRoot = $temporaryRoot.'/repository';
        $this->remoteRoot = $temporaryRoot.'/remote.git';

        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $this->testRoot.'/.scripts',
            $this->testRoot.'/bin',
            $this->testRoot.'/packages/docs/content/upgrade',
        ]);

        $repositoryRoot = \dirname(__DIR__, 4);
        $filesystem->copy($repositoryRoot.'/.scripts/release', $this->testRoot.'/.scripts/release');
        $filesystem->dumpFile($this->testRoot.'/.scripts/release-upgrade-note', "#!/bin/sh\nexit 0\n");
        $filesystem->dumpFile(
            $this->testRoot.'/bin/gh',
            <<<'SH'
                #!/bin/sh
                if [ "$1" = "release" ] && [ "$2" = "view" ]; then
                    echo "https://example.test/releases/$3"
                fi
                SH,
        );
        $filesystem->dumpFile($this->testRoot.'/packages/docs/content/upgrade/next-release.md', "draft\n");
        $filesystem->chmod([
            $this->testRoot.'/.scripts/release',
            $this->testRoot.'/.scripts/release-upgrade-note',
            $this->testRoot.'/bin/gh',
        ], 0755);

        $this->runCommand(['git', 'init', '--bare', '--initial-branch=main', $this->remoteRoot]);
        $this->runCommand(['git', 'init', '--initial-branch=main', $this->testRoot]);
        $this->runCommand(['git', '-C', $this->testRoot, 'add', '.']);
        $this->runCommand([
            'git', '-C', $this->testRoot,
            '-c', 'user.name=Release Test',
            '-c', 'user.email=release@example.test',
            'commit', '-m', 'Initial commit',
        ]);
        $this->runCommand(['git', '-C', $this->testRoot, 'tag', '1.0.0']);
        $filesystem->dumpFile($this->testRoot.'/change.txt', "patch change\n");
        $this->runCommand(['git', '-C', $this->testRoot, 'add', 'change.txt']);
        $this->runCommand([
            'git', '-C', $this->testRoot,
            '-c', 'user.name=Release Test',
            '-c', 'user.email=release@example.test',
            'commit', '-m', 'Patch change',
        ]);
        $this->runCommand(['git', '-C', $this->testRoot, 'remote', 'add', 'origin', $this->remoteRoot]);
        $this->runCommand(['git', '-C', $this->testRoot, 'push', '--set-upstream', 'origin', 'main']);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname($this->testRoot));
    }

    public function testStableReleaseIncrementsThePatchVersion(): void
    {
        $process = new Process(
            [$this->testRoot.'/.scripts/release'],
            $this->testRoot,
            ['PATH' => $this->testRoot.'/bin:'.getenv('PATH')],
        );
        $process->mustRun();

        self::assertStringContainsString('Creating release 1.0.1 (previous: 1.0.0)', $process->getOutput());
        self::assertStringContainsString('Release 1.0.1 created', $process->getOutput());

        $tag = $this->runCommand(['git', '-C', $this->testRoot, 'describe', '--tags', '--exact-match']);
        self::assertSame('1.0.1', trim($tag->getOutput()));

        $remoteTag = $this->runCommand(['git', '--git-dir='.$this->remoteRoot, 'tag', '--list', '1.0.1']);
        self::assertSame('1.0.1', trim($remoteTag->getOutput()));
    }

    /** @param list<string> $command */
    private function runCommand(array $command): Process
    {
        $process = new Process($command);
        $process->mustRun();

        return $process;
    }
}
