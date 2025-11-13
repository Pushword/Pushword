<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

class CaddyfileGenerator extends PageGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        $this->init($host);

        $caddyfile = $this->twig->render($this->apps->get()->getView('/Caddyfile.twig', '@pwStaticGenerator'), [
            'staticDir' => rtrim($this->getStaticDir(), '~'),
            'domain' => $this->app->getMainHost(),
            'domain_snake' => strtolower(str_replace('.', '_', $this->app->getMainHost())),
            'redirections' => $this->getRedirections(),
        ]);
        $this->filesystem->dumpFile($this->getStaticDir().'/.Caddyfile', $caddyfile);
    }

    /**
     * The function cache redirection found during generatePages and
     * format in self::$redirection the content for the Caddyfile.
     */
    protected function getRedirections(): string
    {
        $return = "\n";
        foreach ($this->redirectionManager->get() as $r) {
            $return .= '	redir '.$r[0].' '.$r[1].' '.$r[2]."\n";
        }

        return $return;
    }
}
