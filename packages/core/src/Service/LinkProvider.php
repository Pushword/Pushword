<?php

namespace Pushword\Core\Service;

use Cocur\Slugify\Slugify;
use Exception;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final readonly class LinkProvider
{
    public function __construct(
        private PushwordRouteGenerator $router,
        private SiteRegistry $apps,
        private Twig $twig,
        private Security $security
    ) {
    }

    private function getApp(): SiteConfig
    {
        return $this->apps->get();
    }

    private function currentUserIsAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    /**
     * @param array<string, string>|string|Page $path
     * @param array<string, string>|bool|string $attr
     */
    #[AsTwigFunction('link', needsEnvironment: false, isSafe: ['html'])]
    #[AsTwigFunction('jslink', needsEnvironment: false, isSafe: ['html'])]
    public function renderLink(
        string $anchor,
        array|Page|string $path,
        array|bool|string $attr = [],
        bool $obfuscate = true
    ): string {
        if (\is_bool($attr)) {
            $obfuscate = $attr;
            $attr = [];
        }

        if (\is_array($path)) {
            // dump($path);
            $attr = $path;
            if (! isset($attr['href'])) {
                throw new Exception('attr must contain href for render a link.');
            }

            $path = $attr['href'];
            unset($attr['href']);
        }

        if (\is_string($attr)) {
            $attr = ['class' => $attr];
        }

        if ($path instanceof Page) {
            $path = $this->router->generate($path);
        }

        $class = $attr['class'] ?? '';
        if ($this->currentUserIsAdmin() && ! str_contains($class, 'glightbox')) { // facilite le debug
            $attr['title'] = (isset($attr['title']) ? $attr['title'].' - ' : '').'obf';
        }

        if ($obfuscate) {
            if (str_contains($path, 'mailto:') && false !== filter_var($anchor, \FILTER_VALIDATE_EMAIL)) {
                return $this->renderEncodedMail($anchor);
            }

            $attr = [...$attr, ...['data-rot' => self::obfuscate($path)]];
            $template = $this->getApp()->getView('/component/link_js.html.twig');

            return trim($this->twig->render($template, ['anchor' => $anchor, 'attr' => $attr]));
        }

        $attr = [...$attr, ...['href' => $path]];

        $template = $this->getApp()->getView('/component/link.html.twig');

        return trim($this->twig->render($template, ['anchor' => $anchor, 'attr' => $attr]));
    }

    /** alias for compatibility with v0.* */
    #[AsTwigFunction('encrypt')]
    public static function encrypt(string $path): string
    {
        return self::obfuscate($path);
    }

    #[AsTwigFunction('obfuscate')]
    public static function obfuscate(string $path): string
    {
        if (str_starts_with($path, 'http://')) {
            $path = '-'.substr($path, 7);
        } elseif (str_starts_with($path, 'https://')) {
            $path = '_'.substr($path, 8);
        } elseif (str_starts_with($path, 'mailto:')) {
            $path = '@'.substr($path, 7);
        }

        $rot13path = str_rot13($path);
        $rot13path = str_replace('&nzc;', '&', $rot13path);

        return $rot13path;
    }

    public static function decrypt(string $string): string
    {
        $path = str_rot13($string);

        if (str_starts_with($path, '-')) {
            $path = 'http://'.substr($path, 1);
        } elseif (str_starts_with($path, '_')) {
            $path = 'https://'.substr($path, 1);
        } elseif (str_starts_with($path, '@')) {
            $path = 'mailto:'.substr($path, 1);
        }

        return $path;
    }

    #[AsTwigFunction('mail', needsEnvironment: false, isSafe: ['html'])]
    #[AsTwigFunction('email', needsEnvironment: false, isSafe: ['html'])]
    public function renderEncodedMail(string $mail, string $class = ''): string
    {
        // LINK packages/core/src/templates/component/encoded_mail.html.twig
        $template = $this->getApp()->getView('/component/encoded_mail.html.twig');
        $mail = trim($mail);

        return trim($this->twig->render($template, [
            'mail_readable' => $this->readableEncodedMail($mail),
            'mail_encoded' => str_rot13($mail),
            'mail' => $mail,
            'class' => $class,
        ]));
    }

    private function readableEncodedMail(string $mail): string
    {
        return str_replace('@', '<svg width="1em" height="1em" viewBox="0 0 16 16" class="inline-block" '
        .'fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M13.106 '
        .'7.222c0-2.967-2.249-5.032-5.482-5.032-3.35 0-5.646 2.318-5.646 5.702 0 3.493 2.235 5.708 5.762'
        .' 5.708.862 0 1.689-.123 2.304-.335v-.862c-.43.199-1.354.328-2.29.328-2.926 0-4.813-1.88-4.813-4.798'
        .' 0-2.844 1.921-4.881 4.594-4.881 2.735 0 4.608 1.688 4.608 4.156 0 1.682-.554 2.769-1.416 2.769-.492'
        .' 0-.772-.28-.772-.76V5.206H8.923v.834h-.11c-.266-.595-.881-.964-1.6-.964-1.4 0-2.378 1.162-2.378 2.823 0'
        .' 1.737.957 2.906 2.379 2.906.8 0 1.415-.39 1.709-1.087h.11c.081.67.703 1.148 1.503 1.148 1.572 0 2.57-1.415'
        .' 2.57-3.643zm-7.177.704c0-1.197.54-1.907 1.456-1.907.93 0 1.524.738 1.524 1.907S8.308 9.84 7.371 9.84c-.895'
        .' 0-1.442-.725-1.442-1.914z"/></svg>', $mail);
    }

    #[AsTwigFunction('tel', needsEnvironment: false, isSafe: ['html'])]
    public function renderPhoneNumber(string $number, string $class = ''): string
    {
        $template = $this->apps->get()->getView('/component/phone_number.html.twig');
        $locale = $this->apps->getLocale();

        // For French locale, replace +33 with 0; otherwise keep international format
        $numberReadable = 'fr' === $locale
            ? preg_replace('#^\+\d{2} ?#', '0', $number) ?? throw new Exception() : $number;

        return trim($this->twig->render($template, [
            'number' => str_replace([' ', '&nbsp;', '.'], '', $number),
            'number_readable' => str_replace(' ', '&nbsp;', $numberReadable),
            'class' => $class,
        ]));
    }

    #[AsTwigFunction('bookmark', needsEnvironment: false, isSafe: ['html'])]
    #[AsTwigFunction('anchor', needsEnvironment: false, isSafe: ['html'])]
    public function renderTxtAnchor(string $name): string
    {
        $template = $this->apps->get()->getView('/component/txt_anchor.html.twig');

        $slugify = new Slugify();
        $name = $slugify->slugify($name);

        return $this->twig->render($template, ['name' => $name]);
    }
}
