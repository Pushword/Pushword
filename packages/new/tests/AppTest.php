<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\Admin\PushwordAdminBundle;
use Pushword\AdminBlockEditor\PushwordAdminBlockEditorBundle;
use Pushword\AdvancedMainImage\PushwordAdvancedMainImageBundle;
use Pushword\Api\PushwordApiBundle;
use Pushword\Conversation\PushwordConversationBundle;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\PushwordFlatBundle;
use Pushword\PageScanner\PushwordPageScannerBundle;
use Pushword\StaticGenerator\PushwordStaticGeneratorBundle;
use Pushword\TemplateEditor\PushwordTemplateEditorBundle;
use Pushword\Version\PushwordVersionBundle;
use SQLite3;

/**
 * Verifies that the Pushword installer correctly sets up a new project.
 * Run via: composer test-installer.
 */
final class AppTest extends TestCase
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

    public function testStarterContentIsTheDemoSetNotTheTestFixtures(): void
    {
        $db = new SQLite3(self::$projectDir.'/var/app.db');

        $slugs = [];
        $result = $db->query('SELECT slug, tags FROM page ORDER BY slug');
        self::assertNotFalse($result);
        while (false !== ($row = $result->fetchArray(\SQLITE3_ASSOC))) {
            $slugs[] = $row['slug'];
            self::assertStringContainsString('demo', (string) $row['tags'], $row['slug'].' should be removable with pw:page:delete --tag=demo');
        }

        $db->close();

        self::assertSame(['contact', 'examples', 'getting-started', 'homepage'], $slugs);
    }

    public function testSuperAdminCreated(): void
    {
        $db = new SQLite3(self::$projectDir.'/var/app.db');

        $roles = $db->querySingle("SELECT roles FROM user WHERE email = 'admin@example.tld'");
        $db->close();

        self::assertIsString($roles, 'The installer should create admin@example.tld to log in with.');
        self::assertStringContainsString('ROLE_SUPER_ADMIN', $roles);
    }

    public function testAgentsMdShipsAndClaudeMdSymlinksToIt(): void
    {
        self::assertFileExists(self::$projectDir.'/AGENTS.md');
        self::assertSame('AGENTS.md', readlink(self::$projectDir.'/CLAUDE.md'));

        $content = file_get_contents(self::$projectDir.'/AGENTS.md');
        self::assertNotFalse($content);
        self::assertStringContainsString('vendor/pushword/docs/CLAUDE.md', $content);
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

    /**
     * Pushword has no Flex recipe: a bundle is only in the kernel because its own
     * install.php put it there. Everything this skeleton requires must be, or the
     * install ships code the app never loads — and routes pointing at it.
     */
    public function testEveryShippedBundleIsRegistered(): void
    {
        $content = file_get_contents(self::$projectDir.'/config/bundles.php');
        self::assertNotFalse($content);

        foreach ([
            PushwordCoreBundle::class,
            PushwordAdminBundle::class,
            PushwordAdminBlockEditorBundle::class,
            PushwordAdvancedMainImageBundle::class,
            PushwordApiBundle::class,
            PushwordConversationBundle::class,
            PushwordFlatBundle::class,
            PushwordPageScannerBundle::class,
            PushwordStaticGeneratorBundle::class,
            PushwordTemplateEditorBundle::class,
            PushwordVersionBundle::class,
        ] as $bundle) {
            self::assertStringContainsString($bundle, $content);
        }
    }

    /**
     * Core must come first, and its catch-all page route last, or a bundle's own route
     * never gets a chance to match.
     */
    public function testEveryShippedBundleImportsItsRoutes(): void
    {
        $content = file_get_contents(self::$projectDir.'/config/routes.yaml');
        self::assertNotFalse($content);

        foreach ([
            '@PushwordAdminBundle',
            '@PushwordAdminBlockEditorBundle',
            '@PushwordApiBundle',
            '@PushwordConversationBundle',
            '@PushwordFlatBundle',
            '@PushwordPageScannerBundle',
            '@PushwordStaticGeneratorBundle',
            '@PushwordTemplateEditorBundle',
            '@PushwordVersionBundle',
        ] as $bundle) {
            self::assertStringContainsString($bundle, $content);
        }

        self::assertStringEndsWith(
            "pushword:\n    resource: '@PushwordCoreBundle/Resources/config/routes.yaml'\n",
            $content
        );
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

    /**
     * The Docker question is only asked when a terminal is attached and a daemon
     * answers. Run unattended — CI, a provisioning script, `composer --no-interaction`
     * — the installer must leave no Docker file behind.
     */
    public function testUnattendedInstallShipsNoDockerFile(): void
    {
        foreach (['Dockerfile', 'compose.yaml', 'compose.prod.yaml', '.dockerignore', 'docker'] as $path) {
            self::assertFileDoesNotExist(self::$projectDir.'/'.$path);
        }
    }

    /**
     * …and `pw:docker:init` is the way to get them, which is what the installer points
     * at when the answer is no.
     */
    public function testDockerInitCommandIsAvailable(): void
    {
        $output = [];
        $returnCode = 0;
        exec(
            'php '.escapeshellarg(self::$projectDir.'/bin/console').' pw:docker:init --help 2>&1',
            $output,
            $returnCode
        );

        self::assertSame(0, $returnCode, implode("\n", $output));
    }

    public function testGitignoreUpdated(): void
    {
        $content = file_get_contents(self::$projectDir.'/.gitignore');
        self::assertNotFalse($content);
        self::assertStringContainsString('pushword', $content);
    }
}
