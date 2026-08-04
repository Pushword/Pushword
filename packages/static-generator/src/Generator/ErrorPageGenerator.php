<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

class ErrorPageGenerator extends AbstractGenerator
{
    public function __construct(
        PageRepository $pageRepository,
        Twig $twig,
        ParameterBagInterface $params,
        TranslatorInterface $translator,
        PushwordRouteGenerator $router,
        KernelInterface $kernel,
        SiteRegistry $apps,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct($pageRepository, $twig, $params, $translator, $router, $kernel, $apps);
    }

    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->generateErrorPage();

        foreach ($this->getExtraLocales() as $locale) {
            $this->filesystem->mkdir($this->getStaticDir().'/'.$locale);
            $this->generateErrorPage($locale);
        }
    }

    protected function generateErrorPage(?string $locale = null, string $uri = '404.html'): void
    {
        $filepath = $this->getStaticDir().(null !== $locale ? '/'.$locale : '').'/'.$uri;

        if ($this->filesystem->exists($filepath)) {
            return;
        }

        /** @var LocaleAwareInterface&TranslatorInterface $translator */
        $translator = $this->translator;

        // Set locale for translation during rendering
        $originalLocale = $translator->getLocale();
        if (null !== $locale) {
            $translator->setLocale($locale);
        }

        // Push a synthetic Request so render(controller(...)) works from CLI
        $needsRequest = null === $this->requestStack->getCurrentRequest();
        if ($needsRequest) {
            $this->requestStack->push(Request::create('/'.$uri));
        }

        // The only render the generator runs on THIS kernel: error.html.twig
        // forwards to PageController, whose params carry isStatic. Set on the
        // shared SiteConfig objects for the render only, and restore right after —
        // a worker reuses them for every later request.
        foreach ($this->apps->getAll() as $site) {
            $site->setStatic(true);
        }

        try {
            $html = $this->twig->render('@Twig/Exception/error.html.twig');
            $dump = HtmlMinifier::compress($html);
            $this->filesystem->dumpFile($filepath, $dump);
        } finally {
            foreach ($this->apps->getAll() as $site) {
                $site->resetStatic();
            }

            if ($needsRequest) {
                $this->requestStack->pop();
            }

            $translator->setLocale($originalLocale);
        }
    }
}
