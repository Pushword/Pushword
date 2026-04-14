<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use SQLite3;

/**
 * Verifies that the Pushword installer correctly sets up a new project.
 * Run via: composer test-installer.
 */
class AppTest extends TestCase
{
    private static string $projectDir;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = \dirname(__DIR__);
    }

    public function testDataFixturesCopied(): void
    {
        self::assertFileExists(self::$projectDir.'/src/DataFixtures/AppFixtures.php');
    }

    public function testDatabaseExists(): void
    {
        self::assertFileExists(self::$projectDir.'/var/app.db');
        self::assertGreaterThan(0, filesize(self::$projectDir.'/var/app.db'));
    }

    public function testDatabaseHasData(): void
    {
        $db = new SQLite3(self::$projectDir.'/var/app.db');

        $pageCount = $db->querySingle('SELECT COUNT(*) FROM page');
        self::assertGreaterThan(0, $pageCount, 'Page table should have at least one row');

        $mediaCount = $db->querySingle('SELECT COUNT(*) FROM media');
        self::assertGreaterThan(0, $mediaCount, 'Media table should have at least one row');

        $db->close();
    }

    public function testRoutesConfigured(): void
    {
        $content = file_get_contents(self::$projectDir.'/config/routes.yaml');
        self::assertNotFalse($content);
        self::assertStringContainsString('pushword', $content);
    }

    public function testPushwordConfigExists(): void
    {
        self::assertFileExists(self::$projectDir.'/config/packages/pushword.yaml');
        $content = file_get_contents(self::$projectDir.'/config/packages/pushword.yaml');
        self::assertNotFalse($content);
        self::assertStringContainsString('pushword:', $content);
    }

    public function testBuildManifestExists(): void
    {
        self::assertFileExists(self::$projectDir.'/public/build/manifest.json');
    }

    public function testSqliteConfigured(): void
    {
        $content = file_get_contents(self::$projectDir.'/.env');
        self::assertNotFalse($content);
        self::assertStringContainsString('sqlite:///%kernel.project_dir%/var/app.db', $content);
    }

    public function testAppSecretGenerated(): void
    {
        $content = file_get_contents(self::$projectDir.'/.env');
        self::assertNotFalse($content);
        self::assertDoesNotMatchRegularExpression('/^APP_SECRET=$/m', $content);
    }

    public function testSymfonyKernelBoots(): void
    {
        $output = [];
        $returnCode = 0;
        exec(
            'php '.escapeshellarg(self::$projectDir.'/bin/console').' list 2>&1',
            $output,
            $returnCode
        );
        self::assertSame(0, $returnCode, 'bin/console list should exit with code 0. Output: '.implode("\n", $output));
    }

    public function testBundlesContainPushword(): void
    {
        $content = file_get_contents(self::$projectDir.'/config/bundles.php');
        self::assertNotFalse($content);
        self::assertStringContainsString('PushwordCoreBundle', $content);
    }

    public function testDefaultTemplateRemoved(): void
    {
        self::assertFileDoesNotExist(self::$projectDir.'/templates/base.html.twig');
    }

    public function testMediaDirectoryExists(): void
    {
        self::assertDirectoryExists(self::$projectDir.'/media');
    }

    public function testAssetsDirectoryExists(): void
    {
        self::assertDirectoryExists(self::$projectDir.'/assets');
    }

    public function testPackageJsonExists(): void
    {
        self::assertFileExists(self::$projectDir.'/package.json');
    }

    public function testGitignoreUpdated(): void
    {
        $content = file_get_contents(self::$projectDir.'/.gitignore');
        self::assertNotFalse($content);
        self::assertStringContainsString('pushword', $content);
    }
}
