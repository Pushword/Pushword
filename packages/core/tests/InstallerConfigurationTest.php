<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class InstallerConfigurationTest extends TestCase
{
    public function testLocalPackagesAcceptTheVersionOfAnyBranch(): void
    {
        $root = \dirname(__DIR__, 3);
        $directory = sys_get_temp_dir().'/pushword-installer-configuration-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $filesystem->mkdir($directory);
        $filesystem->copy($root.'/packages/new/composer.json', $directory.'/composer.json');

        try {
            $script = file_get_contents($root.'/.scripts/test-installer');
            self::assertSame(1, preg_match('/\n    php -r \'([^\']+)\' "\$MONOREPO_DIR" "\$TEMP_DIR"/', $script, $matches));
            new Process([\PHP_BINARY, '-r', $matches[1], $root, $directory])->mustRun();

            /** @var array{flex-require: array<string, string>} $original */
            $original = json_decode(file_get_contents($root.'/packages/new/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
            /** @var array{flex-require: array<string, string>, repositories: list<array{url: string, options: array{versions?: array<string, string>}}>} $configured */
            $configured = json_decode(file_get_contents($directory.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);

            foreach ($original['flex-require'] as $name => $version) {
                self::assertSame(str_starts_with($name, 'pushword/') ? '@dev' : $version, $configured['flex-require'][$name], $name);
            }

            self::assertNotEmpty($configured['repositories']);
            foreach ($configured['repositories'] as $repository) {
                self::assertSame(['pushword/'.basename($repository['url']) => '1.0.x-dev'], $repository['options']['versions'] ?? []);
            }
        } finally {
            $filesystem->remove($directory);
        }
    }
}
