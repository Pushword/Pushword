<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class MonorepoValidationTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function dependencyConstraints(): iterable
    {
        yield 'matching package dependencies' => ['*', 0];
        yield 'conflicting package dependencies' => ['^1.0', 1];
    }

    #[DataProvider('dependencyConstraints')]
    public function testValidatesPackagesWithoutMixingInIsolatedTools(string $phpstanConstraint, int $expectedExitCode): void
    {
        $root = \dirname(__DIR__, 3);
        $testRoot = sys_get_temp_dir().'/pushword-monorepo-'.bin2hex(random_bytes(6));
        $filesystem = new Filesystem();

        try {
            $filesystem->mkdir($testRoot.'/packages/twigstan/tools');
            $filesystem->copy($root.'/monorepo-builder.php', $testRoot.'/monorepo-builder.php');
            $filesystem->copy($root.'/packages/twigstan/composer.json', $testRoot.'/packages/twigstan/composer.json');
            $filesystem->copy($root.'/packages/twigstan/tools/composer.json', $testRoot.'/packages/twigstan/tools/composer.json');
            $filesystem->dumpFile($testRoot.'/composer.json', '{"name":"test/root","require":{"pushword/core":"*","phpstan/phpstan":"*"}}');
            $filesystem->dumpFile(
                $testRoot.'/packages/site/composer.json',
                json_encode(['name' => 'test/site', 'require' => ['phpstan/phpstan' => $phpstanConstraint]], \JSON_THROW_ON_ERROR),
            );

            $process = new Process([$root.'/vendor/bin/monorepo-builder', 'validate'], $testRoot);
            $process->run();

            self::assertSame($expectedExitCode, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
            if (0 !== $expectedExitCode) {
                self::assertStringContainsString('phpstan/phpstan', $process->getOutput());
            }
        } finally {
            $filesystem->remove($testRoot);
        }
    }
}
