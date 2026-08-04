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
            'html_max_age' => $this->app->get('static_html_max_age') ?? 10800,
            'html_swr' => $this->app->get('static_html_stale_while_revalidate') ?? 3600,
        ]);
        $this->filesystem->dumpFile($this->getStaticDir().'/.htaccess', $htaccess);

        $this->generateLocaleErrorDocuments();
    }

    /**
     * Serve the localized 404.html (see ErrorPageGenerator) for URLs under
     * /{locale}/. Apache scopes an ErrorDocument to a URL prefix via the
     * directory's own .htaccess — unlike an <If> block in the root file, it
     * needs no AllowOverride beyond the FileInfo the root file already
     * requires, and a file without mod_rewrite directives leaves the root
     * rewrite rules fully inherited.
     */
    protected function generateLocaleErrorDocuments(): void
    {
        foreach ($this->getExtraLocales() as $locale) {
            $htaccess = '';
            foreach ([403, 404, 500] as $code) {
                $htaccess .= 'ErrorDocument '.$code.' /'.$locale.'/404'.\PHP_EOL;
            }

            $this->filesystem->dumpFile($this->getStaticDir().'/'.$locale.'/.htaccess', $htaccess);
        }
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
