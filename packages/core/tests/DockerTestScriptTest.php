<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class DockerTestScriptTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-docker-script-'.bin2hex(random_bytes(6));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->directory.'/.scripts', $this->directory.'/bin', $this->directory.'/scratch']);
        $filesystem->copy(\dirname(__DIR__, 3).'/.scripts/test-docker', $this->directory.'/.scripts/test-docker');
        $filesystem->dumpFile($this->directory.'/bin/docker', <<<'SH'
            #!/bin/sh
            printf '%s\n' "$*" >> "$TMPDIR/docker.log"
            SH);
        $filesystem->chmod($this->directory.'/bin/docker', 0755);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testInstallerFailureDoesNotMountAnAbsentContextDuringCleanup(): void
    {
        new Filesystem()->dumpFile($this->directory.'/.scripts/test-installer', "#!/bin/sh\necho 'Installer failure' >&2\nexit 17\n");
        $process = $this->runScript();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Installer failure', $process->getOutput());
        self::assertSame("rm -f pushword-docker-test\n", file_get_contents($this->directory.'/scratch/docker.log'));
    }

    public function testUsesTheConfiguredTemporaryDirectoryForTheCacheAndContext(): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            $this->directory.'/.scripts/test-installer',
            <<<'SH'
                #!/bin/sh
                mkdir -p "$TMPDIR/com.github.pushword.pushword/installer-cache/project"
                SH,
        );
        $filesystem->dumpFile($this->directory.'/bin/php', <<<'SH'
            #!/bin/sh
            pwd > "$TMPDIR/context.txt"
            exit 17
            SH);
        $filesystem->chmod($this->directory.'/bin/php', 0755);

        $process = $this->runScript();

        self::assertSame(17, $process->getExitCode());
        self::assertStringContainsString('Project: '.$this->directory.'/scratch/com.github.pushword.pushword/installer-cache/project', $process->getOutput());
        $context = trim((string) file_get_contents($this->directory.'/scratch/context.txt'));
        self::assertStringStartsWith($this->directory.'/scratch/pushword-docker-test-', $context);
        self::assertDirectoryDoesNotExist($context);
    }

    private function runScript(): Process
    {
        new Filesystem()->chmod($this->directory.'/.scripts/test-installer', 0755);
        $process = new Process(['bash', $this->directory.'/.scripts/test-docker'], $this->directory, [
            'PATH' => $this->directory.'/bin:'.getenv('PATH'),
            'TMPDIR' => $this->directory.'/scratch',
        ]);
        $process->run();

        return $process;
    }
}
