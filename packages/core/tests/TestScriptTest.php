<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class TestScriptTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir().'/pushword-test-script-'.bin2hex(random_bytes(6));
        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $this->testRoot.'/.scripts',
            $this->testRoot.'/bin',
            $this->testRoot.'/drivers',
            $this->testRoot.'/node_modules',
            $this->testRoot.'/tmp',
        ]);
        foreach (['js-helper', 'admin', 'admin-block-editor', 'admin-monaco-editor', 'quiz'] as $package) {
            $filesystem->mkdir($this->testRoot.'/packages/'.$package.'/node_modules');
        }

        $repositoryRoot = \dirname(__DIR__, 3);
        foreach (['test', 'lib-panther.sh'] as $script) {
            $filesystem->copy($repositoryRoot.'/.scripts/'.$script, $this->testRoot.'/.scripts/'.$script);
        }

        $scripts = [
            'bin/php' => <<<'SH'
                #!/bin/sh
                if [ "$1" = '-r' ]; then
                    printf '%s' "$TMPDIR"
                    exit 0
                fi
                for argument in "$@"; do
                    case "$argument" in
                        vendor/bin/phpunit|vendor/bin/paratest) exit "$STUB_PHPUNIT_EXIT" ;;
                    esac
                done
                exit 0
                SH,
            'bin/composer' => <<<'SH'
                #!/bin/sh
                if [ "$1" = 'validate-monorepo' ]; then
                    exit "$STUB_MONOREPO_EXIT"
                fi
                exit 0
                SH,
            '.scripts/test-installer' => "#!/bin/sh\nexit \"\$STUB_INSTALLER_EXIT\"\n",
            'bin/yarn' => "#!/bin/sh\nexit 0\n",
            'bin/google-chrome' => "#!/bin/sh\necho 'Google Chrome 1.0.0'\n",
            'drivers/chromedriver' => "#!/bin/sh\necho 'ChromeDriver 1.0.0'\n",
        ];
        foreach ($scripts as $path => $source) {
            $filesystem->dumpFile($this->testRoot.'/'.$path, $source);
            $filesystem->chmod($this->testRoot.'/'.$path, 0755);
        }
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->testRoot);
    }

    /** @param list<string> $arguments */
    #[DataProvider('exitCodes')]
    public function testPropagatesFailuresFromEverySuite(array $arguments, int $phpunit, int $monorepo, int $installer, int $expected): void
    {
        $process = new Process(['bash', '-e', $this->testRoot.'/.scripts/test', ...$arguments], $this->testRoot, [
            'PATH' => $this->testRoot.'/bin:'.getenv('PATH'),
            'TMPDIR' => $this->testRoot.'/tmp',
            'TEST_RUN_ID' => basename($this->testRoot),
            'STUB_PHPUNIT_EXIT' => (string) $phpunit,
            'STUB_MONOREPO_EXIT' => (string) $monorepo,
            'STUB_INSTALLER_EXIT' => (string) $installer,
        ]);
        $process->run();

        self::assertSame($expected, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        self::assertStringContainsString('Waiting for test-installer', $process->getOutput());
        self::assertStringContainsString('Waiting for monorepo validation', $process->getOutput());
    }

    /** @return iterable<string, array{list<string>, int, int, int, int}> */
    public static function exitCodes(): iterable
    {
        foreach (['normal' => [], 'prefer-lowest' => ['full']] as $mode => $arguments) {
            yield $mode.' succeeds' => [$arguments, 0, 0, 0, 0];
            yield $mode.' PHPUnit fails' => [$arguments, 7, 0, 0, 7];
            yield $mode.' monorepo fails' => [$arguments, 0, 5, 0, 5];
            yield $mode.' installer fails' => [$arguments, 0, 0, 3, 3];
            yield $mode.' multiple suites fail' => [$arguments, 7, 5, 3, 3];
        }
    }
}
