<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PhpToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;

use Symfony\Component\Process\Process;

/**
 * .scripts/test-installer reuses a cached install while its cache key is unchanged, and
 * the key hashes the dev-app files install.php copies into the site by listing them
 * again. A copy added to install.php but not to that list is then tested against the
 * install built before the file existed or changed.
 */
final class InstallerCacheKeyTest extends TestCase
{
    /** @return iterable<string, array{string|false, string}> */
    public static function temporaryDirectories(): iterable
    {
        yield 'configured' => ['/custom/pushword-temp', '/custom/pushword-temp'];
        yield 'empty' => ['', '/tmp'];
        yield 'unset' => [false, '/tmp'];
    }

    #[DataProvider('temporaryDirectories')]
    public function testInstallerUsesTheConfiguredTemporaryDirectory(string|false $temporaryDirectory, string $expectedBase): void
    {
        $script = file_get_contents(\dirname(__DIR__, 3).'/.scripts/test-installer');
        preg_match_all('/^(?:CACHE_BASE|TEMP_DIR)=.+$/m', $script, $assignments);
        self::assertCount(2, $assignments[0]);
        $process = new Process([
            'bash', '-c', implode("\n", $assignments[0])."\nprintf '%s\\n%s\\n' \"\$CACHE_BASE\" \"\$TEMP_DIR\"",
        ], env: ['TMPDIR' => $temporaryDirectory]);
        $process->mustRun();

        [$cacheDirectory, $installDirectory] = explode("\n", trim($process->getOutput()));
        self::assertSame($expectedBase.'/com.github.pushword.pushword/installer-cache', $cacheDirectory);
        self::assertMatchesRegularExpression('#^'.preg_quote($expectedBase, '#').'/pushword-installer-test-\d+$#', $installDirectory);
    }

    public function testCacheKeyHashesEveryFileInstallPhpCopiesFromDevApp(): void
    {
        $packages = \dirname(__DIR__, 2);

        self::assertSame(
            $this->devAppCopySources($packages.'/core/install.php'),
            $this->hashedDevAppPaths(\dirname($packages).'/.scripts/test-installer'),
        );
    }

    /**
     * String literals only, so a copy left commented out does not count.
     *
     * @return string[]
     */
    private function devAppCopySources(string $installPhp): array
    {
        $sources = [];
        foreach (PhpToken::tokenize(file_get_contents($installPhp)) as $token) {
            if (! $token->is(\T_CONSTANT_ENCAPSED_STRING)) {
                continue;
            }

            preg_match_all('#vendor/pushword/dev-app/([^\s\'"]+)#', $token->text, $matches);
            array_push($sources, ...$matches[1]);
        }

        self::assertNotEmpty($sources, 'No vendor/pushword/dev-app/ source found in install.php.');
        $sources = array_unique($sources);
        sort($sources);

        return $sources;
    }

    /**
     * @return string[]
     */
    private function hashedDevAppPaths(string $script): array
    {
        if (1 !== preg_match('/^COPIED_FILES=\$\(.*?\bfind\s(.+?)\s-type f\b/ms', file_get_contents($script), $find)) {
            self::fail('COPIED_FILES=$(... find <paths> -type f ...) not found in .scripts/test-installer.');
        }

        $paths = [];
        foreach (preg_split('/[\s\\\\]+/', $find[1], -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $argument) {
            $argument = trim($argument, "'\"");
            if (str_starts_with($argument, 'dev-app/')) {
                $paths[] = substr($argument, \strlen('dev-app/'));
            }
        }

        sort($paths);

        return $paths;
    }
}
