<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\FilesizeFormatter;
use Pushword\Core\Utils\HtmlBeautifer;
use Pushword\Core\Utils\MarkdownParser;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        public PushwordRouteGenerator $router,
        private AppPool $apps,
        public Twig $twig,
    ) {
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('md5', 'md5'),
            new TwigFilter('html_entity_decode', 'html_entity_decode'),
            new TwigFilter('slugify', [new Slugify(), 'slugify']),
            new TwigFilter('preg_replace', [self::class, 'pregReplace']),
            new TwigFilter('nice_punctuation', [HtmlBeautifer::class, 'punctuationBeautifer'], self::options()),
            new TwigFilter('markdown', [new MarkdownParser(), 'transform'], self::options()),
            new TwigFilter('unprose', [$this, 'unprose'], self::options()),
            new TwigFilter('escapeTwig', [$this, 'escapeTwig'], self::options()),
        ];
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('view', [$this, 'getView'], ['needs_environment' => false]),
            new TwigFunction('filesize', [FilesizeFormatter::class, 'formatBytes'], self::options()),
            new TwigFunction('contains_link_to', [$this, 'containsLinkTo'], self::options()),
            new TwigFunction('class_exists', 'class_exists'),
        ];
    }

    public function escapeTwig(string $text): string
    {
        $text = htmlspecialchars($text);
        $text = str_replace('{{', '{<!---->{', $text);
        $text = str_replace('{%', '{<!---->%', $text);

        return $text;
    }

    /**
     * @param array<string> $isSafe
     *
     * @return array<string, mixed>
     */
    public static function options(bool $needsEnv = false, array $isSafe = ['html']): array
    {
        return ['is_safe' => $isSafe, 'needs_environment' => $needsEnv];
    }

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
    public static function pregReplace(array|string $subject, array|string $pattern, array|string $replacement): array|string
    {
        return preg_replace($pattern, $replacement, $subject) ?? throw new \Exception();
    }

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

    /**
     * Twig filters.
     */
    public function unprose(string $html): string
    {
        /** @var Twig */
        $twig = $this->twig;
        $unproseClass = $twig->getGlobals()['unprose'] ?? '';

        if ('' === $unproseClass || ! \is_string($unproseClass)) {
            return $html;
        }

        return '<div class="'.$unproseClass.'">'.$html.'</div>';
    }
}
