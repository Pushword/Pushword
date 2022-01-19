<?php

namespace Pushword\Svg;

use Exception;
use PiedWeb\RenderAttributes\AttributesTrait;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    use AttributesTrait;
    use RequiredApps;

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('svg', [$this, 'getSvg'], ['needs_environment' => false, 'is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, string> $attr
     * @param string[]|string       $dir
     */
    public function getSvg(string $name, array $attr = ['class' => 'fill-current w-4 inline-block -mt-1'], $dir = ''): string
    {
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
            throw new Exception('`'.$name.'` (svg) not found.');
        }

        if (! \in_array(\Safe\mime_content_type($file), ['image/svg+xml', 'image/svg'], true)
            || ($svg = file_get_contents($file)) === false) {
            throw new Exception('`'.$name.'` seems not be a valid svg file.');
        }

        return self::replaceOnce('<svg ', '<svg '.self::mapAttributes($attr).' ', $svg);
    }

    private static function replaceOnce(string $needle, string $replace, string $haystack): string
    {
        $pos = strpos($haystack, $needle);
        if (false !== $pos) {
            $haystack = substr_replace($haystack, $replace, $pos, \strlen($needle));
        }

        return $haystack;
    }
}
