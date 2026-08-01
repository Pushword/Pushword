<?php

namespace Pushword\Flat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Network-free paths of bin/pushword-deploy: configuration discovery, argument
 * validation and the database-protection promise baked into the push excludes.
 * The rsync/ssh legs are exercised against real sites with --dry-run.
 */
final class DeployScriptTest extends TestCase
{
    private const string SCRIPT = __DIR__.'/../bin/pushword-deploy';

    private string $siteDir = '';

    protected function setUp(): void
    {
        $this->siteDir = sys_get_temp_dir().'/pushword-deploy-test-'.uniqid();
        mkdir($this->siteDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->siteDir);
    }

    /**
     * @param string[] $args
     */
    private function runScript(array $args, ?string $cwd = null): Process
    {
        $process = new Process(['bash', self::SCRIPT, ...$args], $cwd ?? $this->siteDir);
        $process->run();

        return $process;
    }

    public function testFailsWithoutConf(): void
    {
        $process = $this->runScript(['pull']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('No deploy.conf found', $process->getErrorOutput());
    }

    public function testFailsWhenConfMissesRemote(): void
    {
        file_put_contents($this->siteDir.'/deploy.conf', "REMOTE_PATH=/tmp\n");

        $process = $this->runScript(['pull']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('must set REMOTE and REMOTE_PATH', $process->getErrorOutput());
    }

    public function testNoModePrintsUsage(): void
    {
        $this->writeValidConf();

        $process = $this->runScript([]);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('pushword-deploy pull', $process->getOutput());
        self::assertStringContainsString('--ship-db', $process->getOutput());
    }

    public function testUnknownOptionIsRejected(): void
    {
        $this->writeValidConf();

        $process = $this->runScript(['push', '--nuke']);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('Unknown option: --nuke', $process->getErrorOutput());
    }

    public function testConfIsFoundUpwardFromASubdirectory(): void
    {
        file_put_contents($this->siteDir.'/deploy.conf', "REMOTE_PATH=/tmp\n");
        mkdir($this->siteDir.'/content');

        $process = $this->runScript(['pull'], $this->siteDir.'/content');

        // Reaching the REMOTE validation proves deploy.conf was found upward.
        self::assertStringContainsString('must set REMOTE and REMOTE_PATH', $process->getErrorOutput());
    }

    public function testPushAlwaysProtectsTheDatabase(): void
    {
        $this->writeValidConf();

        // An rsync shim prints its arguments, keeping the test offline while
        // asserting the real command the script builds.
        mkdir($this->siteDir.'/shims');
        file_put_contents($this->siteDir.'/shims/rsync', "#!/bin/bash\necho RSYNC \"\$@\"\n");
        chmod($this->siteDir.'/shims/rsync', 0o755);

        $process = new Process(
            ['bash', self::SCRIPT, 'push', '--dry-run'],
            $this->siteDir,
            ['PATH' => $this->siteDir.'/shims:'.getenv('PATH')],
        );
        $process->run();

        $output = $process->getOutput();
        self::assertStringContainsString('--exclude=var/app.db*', $output, 'The push must always exclude the database');
        self::assertStringContainsString('--exclude=var/flat-sync/', $output, 'The push must always exclude the per-machine sync state');
        self::assertStringContainsString('[dry-run] local: php bin/console pw:flat:sync', $output, 'Dry-run must announce, not execute, the local chain');
    }

    private function writeValidConf(): void
    {
        file_put_contents(
            $this->siteDir.'/deploy.conf',
            "REMOTE=user@example.invalid\nREMOTE_PATH=/srv/site\n",
        );
    }
}
