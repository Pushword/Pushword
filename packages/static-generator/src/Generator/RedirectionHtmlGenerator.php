<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

/**
 * Emit per-path HTML redirect stubs (meta-refresh + canonical link) for static
 * hosts that cannot run server-side rewrites — GitHub Pages, Netlify, S3, …
 * where .htaccess and Caddyfile are ignored.
 *
 * The output is byte-for-byte what jekyll-redirect-from generates, so a site
 * later built through Jekyll stays consistent.
 *
 * Must run after PagesGenerator, which is what populates the RedirectionManager.
 */
class RedirectionHtmlGenerator extends PageGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        $this->init($host);

        $redirections = $this->redirectionManager->get();
        if ([] === $redirections) {
            return;
        }

        $template = $this->apps->get()->getView('/redirect.html.twig', '@PushwordStatic');
        $lang = $this->app->getLocale();

        foreach ($redirections as [$from, $to]) {
            $file = $this->resolveStubPath($from);
            if (null === $file) {
                continue;
            }

            $this->filesystem->dumpFile($file, $this->twig->render($template, ['to' => $to, 'lang' => $lang]));
        }
    }

    /**
     * Map a root-relative redirect source ("/old-slug") to the file a static host
     * serves for it ("…/old-slug.html"), mirroring PagesGenerator's "{slug}.html".
     * Returns null for the homepage, which must not be shadowed.
     */
    private function resolveStubPath(string $from): ?string
    {
        $slug = ltrim($from, '/');

        if ('' === $slug) {
            return null;
        }

        $relative = str_ends_with($slug, '/') ? $slug.'index.html' : $slug.'.html';

        return $this->getStaticDir().'/'.$relative;
    }
}
