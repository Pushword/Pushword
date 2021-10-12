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
            if ($this->app->getLocale() == $locale) {
                continue;
            }
            $this->filesystem->mkdir($this->getStaticDir().'/'.$locale);
            $this->generateErrorPage($locale);
        }
    }

    // TODO : make it useful when using a .htaccess else disable it
    protected function generateErrorPage($locale = null, $uri = '404.html')
    {
        if (null !== $locale) {
            $request = $this->requestStack->getCurrentRequest();
            if (null === $request) {
                $request = new Request();
            }
            $request->setLocale($locale);
            $this->requestStack->push($request);
        }

        $dump = $this->parser->compress($this->twig->render('@Twig/Exception/error.html.twig'));
        $this->filesystem->dumpFile($this->getStaticDir().(null !== $locale ? '/'.$locale : '').'/'.$uri, $dump);
    }
}
