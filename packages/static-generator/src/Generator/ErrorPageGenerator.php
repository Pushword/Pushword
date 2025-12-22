<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    protected function generateErrorPage(?string $locale = null, string $uri = '404.html'): void
    {
        $filepath = $this->getStaticDir().(null !== $locale ? '/'.$locale : '').'/'.$uri;

        if (file_exists($filepath)) {
            return;
        }

        /** @var LocaleAwareInterface&TranslatorInterface $translator */
        $translator = $this->translator;

        // Set locale for translation during rendering
        $originalLocale = $translator->getLocale();
        if (null !== $locale) {
            $translator->setLocale($locale);
        }

        try {
            $html = $this->twig->render('@Twig/Exception/error.html.twig');
            $dump = HtmlMinifier::compress($html);
            $this->filesystem->dumpFile($filepath, $dump);
        } finally {
            $translator->setLocale($originalLocale);
        }
    }
}
