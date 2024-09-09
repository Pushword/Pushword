<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LinkExtension extends AbstractExtension
{
    public function __construct(
        public PushwordRouteGenerator $router,
        private readonly AppPool $apps,
        public Twig $twig,
        private readonly LinkProvider $linkProvider,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('jslink', $this->linkProvider->renderLink(...), AppExtension::options()), // alias link
            new TwigFunction('link', $this->linkProvider->renderLink(...), AppExtension::options()),
            new TwigFunction('encrypt', [LinkProvider::class, 'encrypt']),
            new TwigFunction('obfuscate', [LinkProvider::class, 'obfuscate']),
            new TwigFunction('mail', $this->linkProvider->renderEncodedMail(...), AppExtension::options()),
            new TwigFunction('email', $this->linkProvider->renderEncodedMail(...), AppExtension::options()),
            new TwigFunction('tel', $this->linkProvider->renderPhoneNumber(...), AppExtension::options()),
            new TwigFunction('bookmark', $this->renderTxtAnchor(...), AppExtension::options()), // used ?
            new TwigFunction('anchor', $this->renderTxtAnchor(...), AppExtension::options()), // alias bookmark
        ];
    }

    public function renderTxtAnchor(string $name): string
    {
        $template = $this->apps->get()->getView('/component/txt_anchor.html.twig');

        $slugify = new Slugify();
        $name = $slugify->slugify($name);

        return $this->twig->render($template, ['name' => $name]);
    }
}
