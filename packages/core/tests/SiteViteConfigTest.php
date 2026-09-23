<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;

/**
 * install.php copies packages/dev-app/vite.config.js into every new site. Vite reads
 * `base` only at the top level of defineConfig(): nested under `build` it is ignored,
 * vite-plugin-symfony falls back to `/build/`, and every URL in entrypoints.json points
 * away from the directory the files are written to.
 */
final class SiteViteConfigTest extends TestCase
{
    public function testBaseIsTheUrlOfTheBuildOutputDirectory(): void
    {
        $config = file_get_contents(\dirname(__DIR__, 2).'/dev-app/vite.config.js');

        // Prettier indents defineConfig()'s own options by two spaces, build's by four.
        if (1 !== preg_match("/^  base: '([^']+)',$/m", $config, $base)) {
            self::fail('base must be a top-level option of defineConfig(), not nested in build.');
        }

        if (1 !== preg_match("/^    outDir: '([^']+)',$/m", $config, $outDir)) {
            self::fail('build.outDir not found.');
        }

        self::assertSame('public'.rtrim($base[1], '/'), $outDir[1]);
    }
}
