<?php

namespace Pushword\Core\Twig;

use Exception;
use PiedWeb\RenderAttributes\Attribute;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\FontAwesome5To6 as SvgFontAwesome5To6;

use function Safe\mime_content_type;

use Twig\Attribute\AsTwigFunction;

class SVGExtension
{
    /**
     * Icon file contents, keyed by the search path they were resolved against.
     * A page repeats the same icon a lot (~20 svg() calls, a handful of icons),
     * and reading one costs a mime_content_type() — which opens the file — plus
     * a file_get_contents(). Icons are static files, so read each one once.
     *
     * @var array<string, string>
     */
    private array $svgCache = [];

    public function __construct(private readonly SiteRegistry $apps)
    {
    }

    /**
     * @param array<string, string>|string $attr
     * @param string[]|string              $dir
     */
    #[AsTwigFunction('svg', needsEnvironment: false, isSafe: ['html'])]
    public function getSvg(string $name, array|string $attr = ['class' => 'fill-current w-4 inline-block -mt-1'], array|string $dir = '', bool $retryWithFontAwesome5IconsRenamed = true): string
    {
        if (\is_string($attr)) {
            $attr = ['class' => str_contains($attr, 'block') ? $attr : 'fill-current w-4 inline-block -mt-1 '.$attr];
        }

        $dirs = '' !== $dir ? $dir : $this->apps->get()->getStringList('svg_dir');

        if (! \is_array($dirs)) {
            $dirs = [$dirs];
        }

        $svg = $this->loadSvg($name, $dirs, $retryWithFontAwesome5IconsRenamed);

        return $this->replaceOnce('<svg ', '<svg '.Attribute::renderAll($attr).' ', $svg);
    }

    /**
     * Resolve an icon name to its file contents. Keyed on the resolved search
     * path, not on the `dir` argument: `svg_dir` is per app, so the same name
     * can point at different files from one host to the next.
     *
     * @param string[] $dirs
     */
    private function loadSvg(string $name, array $dirs, bool $retryWithFontAwesome5IconsRenamed): string
    {
        $cacheKey = implode('|', $dirs).'|'.$name;

        if (isset($this->svgCache[$cacheKey])) {
            return $this->svgCache[$cacheKey];
        }

        $file = $this->resolveFile($name, $dirs);

        if (null === $file) {
            if ($retryWithFontAwesome5IconsRenamed) {
                return $this->svgCache[$cacheKey] = $this->loadSvg(SvgFontAwesome5To6::convertNameFromFontAwesome5To6($name), $dirs, false);
            }

            return $this->svgCache[$cacheKey] = 'question' !== $name
                ? $this->loadSvg('question', $dirs, $retryWithFontAwesome5IconsRenamed)
                : throw new Exception('`'.$name.'` (svg) not found.');
        }

        if (! \in_array(mime_content_type($file), ['image/svg+xml', 'image/svg'], true)
            || ($svg = file_get_contents($file)) === false) {
            throw new Exception('`'.$name.'` seems not be a valid svg file.');
        }

        return $this->svgCache[$cacheKey] = $svg;
    }

    /**
     * First directory holding the icon wins.
     *
     * @param string[] $dirs
     */
    private function resolveFile(string $name, array $dirs): ?string
    {
        foreach ($dirs as $dir) {
            $file = $dir.'/'.$name.'.svg';
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    private function replaceOnce(string $needle, string $replace, string $haystack): string
    {
        $pos = strpos($haystack, $needle);
        if (false !== $pos) {
            return substr_replace($haystack, $replace, $pos, \strlen($needle));
        }

        return $haystack;
    }
}
