<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Command\DockerInitCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

final class DockerInitCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/pushword-docker-init-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    public function testCopiesTheWholeSkeletonIncludingDotfilesAndSubdirectories(): void
    {
        self::assertSame(Command::SUCCESS, $this->initDocker());

        foreach ([
            'Dockerfile',
            'compose.yaml',
            'compose.prod.yaml',
            '.dockerignore',
            'docker/docker-entrypoint.sh',
            'docker/php.dev.ini',
            'docker/php.prod.ini',
        ] as $expected) {
            self::assertFileExists($this->projectDir.'/'.$expected);
        }
    }

    public function testEntrypointIsExecutable(): void
    {
        $this->initDocker();

        self::assertTrue(is_executable($this->projectDir.'/docker/docker-entrypoint.sh'));
    }

    /**
     * The entrypoint seeds an unseeded volume. A project installed on the host already
     * has its content and its admin account, so the marker has to be there before the
     * first `docker compose up` bind-mounts it.
     */
    public function testMarksTheProjectAsAlreadySeeded(): void
    {
        $this->initDocker();

        self::assertFileExists($this->projectDir.'/var/.pushword-seeded');
    }

    public function testKeepsAnEditedFileUnlessForced(): void
    {
        new Filesystem()->dumpFile($this->projectDir.'/compose.yaml', 'mine');

        $output = new BufferedOutput();
        $this->initDocker($output);

        self::assertSame('mine', file_get_contents($this->projectDir.'/compose.yaml'));
        self::assertStringContainsString('compose.yaml', $output->fetch());
        // The rest still landed.
        self::assertFileExists($this->projectDir.'/Dockerfile');
    }

    public function testForceOverwrites(): void
    {
        new Filesystem()->dumpFile($this->projectDir.'/compose.yaml', 'mine');

        $this->initDocker(force: true);

        self::assertStringNotContainsString('mine', (string) file_get_contents($this->projectDir.'/compose.yaml'));
    }

    private function initDocker(?BufferedOutput $output = null, bool $force = false): int
    {
        $command = new DockerInitCommand(
            $this->projectDir,
            \dirname(__DIR__, 3).'/dev-app/docker-skeleton',
        );

        return $command(new ArrayInput([]), $output ?? new BufferedOutput(), $force);
    }
}
