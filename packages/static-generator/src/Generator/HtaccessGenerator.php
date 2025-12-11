<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

class HtaccessGenerator extends PageGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        $this->init($host);

        $htaccess = $this->twig->render($this->apps->get()->getView('/htaccess.twig', '@PushwordStatic'), [
            'domain' => $this->app->getMainHost(),
            'redirections' => $this->getRedirections(),
            'image_fallback_order' => $this->getImageFallbackOrder(),
        ]);
        $this->filesystem->dumpFile($this->getStaticDir().'/.htaccess', $htaccess);
    }

    /**
     * Determine the fallback order for image formats based on configuration.
     * Returns the formats that should be tried in order (avif, webp, original).
     *
     * @return array{webp_fallback: string[], avif_fallback: string[]}
     */
    protected function getImageFallbackOrder(): array
    {
        $filterSets = $this->params->get('pw.image_filter_sets');

        // Check what formats are commonly configured across filters
        $hasAvif = false;
        $hasWebp = false;
        $hasOriginal = false;

        foreach ($filterSets as $filter) {
            /** @var string[] $formats */
            $formats = $filter['formats'];
            if (\in_array('avif', $formats, true)) {
                $hasAvif = true;
            }

            if (\in_array('webp', $formats, true)) {
                $hasWebp = true;
            }

            if (\in_array('original', $formats, true)) {
                $hasOriginal = true;
            }
        }

        // Determine fallback chain for each format
        // If webp is requested but doesn't exist: try avif, then original
        $webpFallback = [];
        if ($hasAvif) {
            $webpFallback[] = 'avif';
        }

        if ($hasOriginal) {
            $webpFallback[] = 'original';
        }

        // If avif is requested but doesn't exist: try webp, then original
        $avifFallback = [];
        if ($hasWebp) {
            $avifFallback[] = 'webp';
        }

        if ($hasOriginal) {
            $avifFallback[] = 'original';
        }

        return [
            'webp_fallback' => $webpFallback,
            'avif_fallback' => $avifFallback,
        ];
    }

    /**
     * The function cache redirection found during generatePages and
     * format in self::$redirection the content for the .htaccess.
     */
    protected function getRedirections(): string
    {
        $return = '';
        foreach ($this->redirectionManager->get() as $r) {
            $return .= 'Redirect '.$r[2].' '.$r[0].' '.$r[1].\PHP_EOL;
        }

        return $return;
    }
}
