<?php

declare(strict_types=1);

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
 * markup driven by old JS. Most Vite-built assets carry a content hash in their
 * filename; Mermaid's stable entry modules need a content stamp in the URL.
 */
final readonly class AssetExtension
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * Stamp assets with their mtime, or their content when a build can preserve
     * timestamps while replacing the file. Missing assets use the current time
     * so a later `assets:install` never inherits a stale cache entry.
     */
    #[AsTwigFunction('versionedAsset')]
    public function versionedAsset(string $assetPath, bool $contentHash = false): string
    {
        $absolutePath = $this->projectDir.'/public/'.ltrim($assetPath, '/');
        $version = (string) \time();
        if (\is_file($absolutePath)) {
            $version = $contentHash ? hash_file('sha256', $absolutePath) : (string) \filemtime($absolutePath);
        }

        return sprintf('%s?v=%s', $assetPath, $version);
    }
}
