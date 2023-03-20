<?php

namespace Pushword\StaticGenerator\Generator;

use Symfony\Component\HttpFoundation\Request;

class ErrorPageGenerator extends AbstractGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->generateErrorPage();

        foreach ($this->app->getLocales() as $locale) {
            if ($this->app->getLocale() === $locale) {
                continue;
            }

            $this->filesystem->mkdir($this->getStaticDir().'/'.$locale);
            $this->generateErrorPage($locale);
        }
    }

    // TODO : make it useful when using a .htaccess else disable it
    protected function generateErrorPage(?string $locale = null, string $uri = '404.html'): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (! $request instanceof \Symfony\Component\HttpFoundation\Request) {
            $request = new Request();
        }

        if (null !== $locale) {
            $request->setLocale($locale);
        }

        $this->requestStack->push($request);

        $filepath = $this->getStaticDir().(null !== $locale ? '/'.$locale : '').'/'.$uri;

        if (file_exists($filepath)) {
            return;
        }

        $dump = $this->parser->compress($this->twig->render('@Twig/Exception/error.html.twig'));
        $this->filesystem->dumpFile($filepath, $dump);
    }
}
