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

    public function getFunctions()
    {
        return [
            new TwigFunction('svg', [$this, 'getSvg'], ['needs_environment' => false, 'is_safe' => ['html']]),
        ];
    }

    public function getSvg(string $name, $attr = ['class' => 'fill-current']): string
    {
        $dir = $this->apps->get()->get('svg_dir');

        $couldBeIn = ['solid/', 'regular/', 'brands/', ''];

        foreach ($couldBeIn as $subDir) {
            $file = $dir.'/'.$subDir.$name.'.svg';
            if (file_exists($file)) {
                break;
            }
            unset($file);
        }

        if (! isset($file)) {
            throw new Exception('`'.$name.'` (svg) not found.');
        }

        $svg = file_get_contents($file);

        $svg = self::replaceOnce('<svg ', '<svg '.self::mapAttributes($attr).' ', $svg);

        return $svg;
    }

    private static function replaceOnce(string $needle, string $replace, string $haystack)
    {
        $pos = strpos($haystack, $needle);
        if (false !== $pos) {
            $haystack = substr_replace($haystack, $replace, $pos, \strlen($needle));
        }

        return $haystack;
    }
}
