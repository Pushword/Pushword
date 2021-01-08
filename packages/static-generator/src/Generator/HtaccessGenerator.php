<?php

namespace Pushword\StaticGenerator\Generator;

class HtaccessGenerator extends PageGenerator
{
    public function generate(?string $host = null): void
    {
        $this->init($host);

        $htaccess = $this->twig->render('@pwStaticGenerator/htaccess.twig', [
            'domain' => $this->app->getMainHost(),
            'redirections' => $this->getRedirections(),
        ]);
        $this->filesystem->dumpFile($this->getStaticDir().'/.htaccess', $htaccess);
    }

    /**
     * The function cache redirection found during generatePages and
     * format in self::$redirection the content for the .htaccess.
     */
    protected function getRedirections(): string
    {
        $return = '';
        foreach ($this->redirectionManager->get() as $r) {
            $return .= 'Redirect '.$r[2].' '.$r[0].' '.$r[1].PHP_EOL;
        }

        return $return;
    }
}
