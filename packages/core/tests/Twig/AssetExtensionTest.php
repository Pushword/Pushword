<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Twig\AssetExtension;

final class AssetExtensionTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/pw-asset-ext-'.uniqid();
        mkdir($this->projectDir.'/public/bundles/acme', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (['/public/bundles/acme/app.js', '/public/bundles/acme', '/public/bundles', '/public', ''] as $path) {
            $target = $this->projectDir.$path;
            if (is_file($target)) {
                unlink($target);
            } elseif (is_dir($target)) {
                rmdir($target);
            }
        }
    }

    public function testItStampsAPublishedAssetWithItsMtime(): void
    {
        $file = $this->projectDir.'/public/bundles/acme/app.js';
        file_put_contents($file, 'console.log(1)');
        touch($file, 1234567890);

        $extension = new AssetExtension($this->projectDir);

        // The path is returned untouched, so it stays resolvable; only the query is added.
        self::assertSame('/bundles/acme/app.js?v=1234567890', $extension->versionedAsset('/bundles/acme/app.js'));
    }

    public function testTheStampChangesWhenTheFileChanges(): void
    {
        $file = $this->projectDir.'/public/bundles/acme/app.js';
        file_put_contents($file, 'console.log(1)');
        touch($file, 1234567890);

        $extension = new AssetExtension($this->projectDir);
        $before = $extension->versionedAsset('/bundles/acme/app.js');

        // A deploy republishes the bundle: same URL, different bytes. The stamp has
        // to move, or a CDN keeps serving the previous release for days.
        file_put_contents($file, 'console.log(2)');
        touch($file, 1234567999);

        self::assertNotSame($before, $extension->versionedAsset('/bundles/acme/app.js'));
    }

    public function testAMissingAssetIsNeverStampedWithAStaleVersion(): void
    {
        $extension = new AssetExtension($this->projectDir);

        // assets:install has not published it yet — fall back to now, so the absent
        // file cannot pin a cache entry under a version that never changes.
        self::assertMatchesRegularExpression(
            '#^/bundles/acme/missing\.js\?v=\d+$#',
            $extension->versionedAsset('/bundles/acme/missing.js')
        );
    }

    public function testItAcceptsAPathWithoutALeadingSlash(): void
    {
        $file = $this->projectDir.'/public/bundles/acme/app.js';
        file_put_contents($file, 'console.log(1)');
        touch($file, 1234567890);

        $extension = new AssetExtension($this->projectDir);

        self::assertSame('bundles/acme/app.js?v=1234567890', $extension->versionedAsset('bundles/acme/app.js'));
    }
}
