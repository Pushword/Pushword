<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

class CopierGenerator extends AbstractGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $symlink = $this->mustSymlink();
        $entries = $this->app->getStringList('static_copy');

        $issetFavicon = false;

        foreach ($entries as $entry) {
            if ('favicon.ico' === $entry) {
                $issetFavicon = true;
            }

            $this->copyOrSymlink($entry, $symlink);
        }

        // permits to add a favicons to the root dir without extra config if the favicons is in the assets folder
        // else configure  in in static_copy
        if ($issetFavicon || $this->filesystem->exists($this->getStaticDir().'/favicon.ico')) {
            return;
        }

        $commonFaviconsSpotList = [
            'assets/favicon.ico',
            'assets/favicons/favicon.ico',
        ];
        foreach ($commonFaviconsSpotList as $faviconSpot) {
            if ($this->filesystem->exists($this->publicDir.'/'.$faviconSpot)) {
                $this->copyOrSymlink($faviconSpot, $symlink, 'favicon.ico');
            }
        }
    }

    private function copyOrSymlink(string $entry, bool $symlink, ?string $to = null): void
    {
        $from = $this->publicDir.'/'.$entry;
        $to = $this->getStaticDir().'/'.($to ?? $entry);

        if (! $this->filesystem->exists($from)) {
            return;
        }

        if ($symlink) {
            $relativePath = $this->filesystem->makePathRelative(\dirname($from), \dirname($to));
            $this->filesystem->symlink($relativePath.basename($from), $to);

            return;
        }

        if (is_file($from)) {
            $this->filesystem->copy($from, $to);

            return;
        }

        $this->filesystem->mirror($from, $to);
    }
}
