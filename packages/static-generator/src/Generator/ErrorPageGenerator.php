<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Symfony\Component\HttpFoundation\Request;

class ErrorPageGenerator extends AbstractGenerator
{
    #[Override]
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
        if (! $request instanceof Request) {
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

        $html = $this->twig->render('@Twig/Exception/error.html.twig');
        $dump = HtmlMinifier::compress($html);
        $this->filesystem->dumpFile($filepath, $dump);
    }
}
