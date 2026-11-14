<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

final class ReleaseTagTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function releaseTags(): iterable
    {
        yield 'stable' => ['1.0.0', 0];
        yield 'next shared release' => ['1.0.109', 0];
        yield 'release candidate' => ['1.0.0-rc899', 0];
        yield 'first package-only patch' => ['1.0.108.1', 1];
        yield 'second package-only patch' => ['1.0.108.2', 1];
        yield 'unversioned tag' => ['main', 1];
        yield 'empty tag' => ['', 1];
    }

    #[DataProvider('releaseTags')]
    public function testSplitWorkflowValidatesTheReleaseTag(string $tag, int $expectedExitCode): void
    {
        $repositoryRoot = \dirname(__DIR__, 4);
        $workflow = Yaml::parseFile($repositoryRoot.'/.github/workflows/split-monorepo.yaml');
        self::assertIsArray($workflow);
        $jobs = $workflow['jobs'];
        self::assertIsArray($jobs);
        $validation = $jobs['validate_release'];
        self::assertIsArray($validation);
        self::assertSame('${{ !github.event.deleted }}', $validation['if']);
        $split = $jobs['split_monorepo'];
        self::assertIsArray($split);
        self::assertSame('validate_release', $split['needs']);
        $steps = $validation['steps'];
        self::assertIsArray($steps);
        $step = $steps[1];
        self::assertIsArray($step);
        $command = $step['run'];
        self::assertIsString($command);

        $process = new Process(['bash', '-e', '-c', $command], $repositoryRoot, ['RELEASE_TAG' => $tag]);
        $process->run();

        self::assertSame($expectedExitCode, $process->getExitCode(), $process->getErrorOutput());
        if (0 !== $expectedExitCode) {
            self::assertStringContainsString('Invalid Pushword release tag', $process->getErrorOutput());
        }
    }
}
