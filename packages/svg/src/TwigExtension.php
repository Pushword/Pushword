<?php

namespace Pushword\Svg;

use PiedWeb\RenderAttributes\Attribute;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Pushword\Svg\FontAwesome5To6 as SvgFontAwesome5To6;

use function Safe\mime_content_type;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    use RequiredApps;

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('svg', $this->getSvg(...), ['needs_environment' => false, 'is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, string>|string $attr
     * @param string[]|string              $dir
     */
    public function getSvg(string $name, array|string $attr = ['class' => 'fill-current w-4 inline-block -mt-1'], array|string $dir = '', bool $retryWithFontAwesome5IconsRenamed = true): string
    {
        if (\is_string($attr)) {
            $attr = ['class' => str_contains($attr, 'block') ? $attr : 'fill-current w-4 inline-block -mt-1 '.$attr];
        }

        $dirs = '' !== $dir ? $dir : $this->apps->get()->get('svg_dir');

        if (! \is_array($dirs)) {
            $dirs = [$dirs];
        }

        $file = null;
        foreach ($dirs as $d) {
            $file = $d.'/'.$name.'.svg';
            if (file_exists($file)) {
                break;
            }

            $file = null;
        }

        if (null === $file) {
            if ($retryWithFontAwesome5IconsRenamed) {
                return $this->getSvg(SvgFontAwesome5To6::convertNameFromFontAwesome5To6($name), $attr, $dir, false);
            }

            return 'question' !== $name
                ? $this->getSvg('question', $attr, $dir, $retryWithFontAwesome5IconsRenamed)
                : throw new \Exception('`'.$name.'` (svg) not found.');
        }

        if (! \in_array(mime_content_type($file), ['image/svg+xml', 'image/svg'], true)
            || ($svg = file_get_contents($file)) === false) {
            throw new \Exception('`'.$name.'` seems not be a valid svg file.');
        }

        return $this->replaceOnce('<svg ', '<svg '.Attribute::renderAll($attr).' ', $svg);
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
