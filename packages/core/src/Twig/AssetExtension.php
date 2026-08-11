<?php

namespace Pushword\Core\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * Cache-busting for assets published at a stable URL — the `public/bundles/…`
 * files `assets:install` copies out of each bundle.
 *
 * Front-ends serve `*.js`/`*.css` with a long public max-age, and those paths are
 * unauthenticated whatever the page embedding them requires, so a CDN caches them
 * happily. Without a version query the URL never changes across releases and the
 * CDN keeps handing out the previous release's file for days after a deploy — new
 * markup driven by old JS. Vite-built assets carry a content hash and need none of
 * this; hand-published bundle assets do.
 */
final readonly class AssetExtension
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * The asset's path with its published file's mtime appended. Falls back to
     * time() when the file is absent, so an asset `assets:install` has not
     * published yet never sticks in a cache under a stale stamp.
     */
    #[AsTwigFunction('versionedAsset')]
    public function versionedAsset(string $assetPath): string
    {
        $absolutePath = $this->projectDir.'/public/'.ltrim($assetPath, '/');
        $version = \is_file($absolutePath) ? (string) \filemtime($absolutePath) : (string) \time();

        return sprintf('%s?v=%s', $assetPath, $version);
    }
}
