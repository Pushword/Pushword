<?php

namespace Pushword\Svg;

use Exception;
use Pushword\Core\Component\App\AppPool;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    private AppPool $apps;

    public function getFunctions()
    {
        return [
            new TwigFunction('svg', [$this, 'getSvg'], ['needs_environment' => false, 'is_safe' => ['html']]),
        ];
    }

    /** @required */
    public function setApps(AppPool $apps)
    {
        $this->apps = $apps;
    }

    public function getSvg(string $name): string
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

        return file_get_contents($file);
    }
}
