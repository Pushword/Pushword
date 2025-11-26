<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\FilesizeFormatter;
use Pushword\Core\Utils\HtmlBeautifer;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final class AppExtension
{
    public function __construct(
        public PushwordRouteGenerator $router,
        private AppPool $apps,
        public Twig $twig,
    ) {
    }

    #[AsTwigFunction('codeBlock', isSafe: ['html'], needsEnvironment: false)]
    public function codeBlock(string $code, string $language = 'js', string $id = ''): string
    {
        return
            '<pre class="microlight"'.('' !== $id ? ' id="'.$id.'"' : '').'>'
            .'<code class="language-'.$language.'">'.$this->escapeTwig($code).'</code>'
            .'</pre>';
    }

    #[AsTwigFilter('escapeTwig', isSafe: ['html'], needsEnvironment: false)]
    public function escapeTwig(string $text): string
    {
        $text = htmlspecialchars($text);
        $text = str_replace('{{', '{<!---->{', $text);
        $text = str_replace('{%', '{<!---->%', $text);

        return $text;
    }

    #[AsTwigFunction('view', needsEnvironment: false)]
    public function getView(string $path, ?string $fallback = null): string
    {
        return null !== $fallback ? $this->apps->get()->getView($path, $fallback)
            : $this->apps->get()->getView($path);
    }

    /**
     * @param string[]|string                                     $subject
     * @param array<array-key, non-empty-string>|non-empty-string $pattern
     * @param string[]|string                                     $replacement
     *
     * @return ($subject is array ? string[] : string)
     */
    #[AsTwigFilter('preg_replace')]
    public static function pregReplace(array|string $subject, array|string $pattern, array|string $replacement): array|string
    {
        return preg_replace($pattern, $replacement, $subject) ?? throw new \Exception();
    }

    #[AsTwigFunction('contains_link_to')]
    public function containsLinkTo(string $slug, ?string $content = null): bool
    {
        if (null === $content) {
            $content = $this->apps->getCurrentPage()?->getMainContent() ?? '';
        }

        $path = $this->router->generate($slug);

        return str_contains($content, '"'.$path.'"')
            || str_contains($content, '='.$path.'>')
            || str_contains($content, '='.$path.' ')
            || str_contains($content, "'$slug'")
            || str_contains($content, '"/'.$slug.'"')
            || str_contains($content, '"/'.$slug.'\"');
    }

    #[AsTwigFunction('breadcrumbJsonLd', isSafe: ['html'])]
    public function generateBreadcrumbJsonLd(Page $page): string
    {
        $breadcrumbs = [];
        $position = 1;
        $currentPage = $page;

        while (null !== $currentPage) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $currentPage->getName() ?: $currentPage->getH1() ?: $currentPage->getTitle(),
                'item' => $this->router->generate($currentPage, true),
            ];

            $currentPage = $currentPage->getParentPage();
            ++$position;
        }

        $breadcrumbs = array_reverse($breadcrumbs);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ];

        return \Safe\json_encode($jsonLd, \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT);
    }

    #[AsTwigFilter('md5')]
    public function md5Filter(string $string): string
    {
        return md5($string);
    }

    #[AsTwigFilter('html_entity_decode')]
    public function htmlEntityDecodeFilter(string $string): string
    {
        return html_entity_decode($string);
    }

    #[AsTwigFilter('slugify')]
    public function slugifyFilter(string $string): string
    {
        return (new Slugify())->slugify($string);
    }

    #[AsTwigFilter('nice_punctuation')]
    public function nicePunctuationFilter(string $string): string
    {
        return HtmlBeautifer::punctuationBeautifer($string);
    }

    #[AsTwigFunction('filesize')]
    public function filesizeFunction(int $bytes): string
    {
        return FilesizeFormatter::formatBytes($bytes);
    }

    #[AsTwigFunction('class_exists')]
    public function classExistsFunction(string $class): bool
    {
        return class_exists($class);
    }

    #[AsTwigFunction('base')]
    public function getBase(bool $live = true): string
    {
        return $live ? $this->apps->get()->getStr('base_live_url')
            : $this->apps->get()->getStr('base_url');
    }

    /**
     * @param scalar $value
     */
    #[AsTwigFunction('integer')]
    #[AsTwigFilter('integer')]
    public function integer(mixed $value): int
    {
        return (int) $value;
    }

    /**
     * @param scalar $value
     */
    #[AsTwigFunction('float')]
    #[AsTwigFilter('float')]
    public function float(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * @param scalar $value
     */
    #[AsTwigFunction('boolean')]
    #[AsTwigFilter('boolean')]
    public function boolean(mixed $value): bool
    {
        return (bool) $value;
    }
}
