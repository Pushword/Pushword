<?php

namespace Pushword\Core\Twig;

use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final class ViteExtension
{
    #[AsTwigFunction('vite_style', isSafe: ['html'], needsEnvironment: true)]
    public function renderViteStylesheet(Twig $twig, string $path): string
    {
        $functions = $twig->getFunctions();
        $return = isset($functions['vite_entry_link_tags'])
            ? $functions['vite_entry_link_tags']->getCallable()($path) // @phpstan-ignore-line
            : null;

        assert(is_string($return) || null === $return);

        return $return ?? '<!--You must install vite bundle to use this function-->';
    }

    #[AsTwigFunction('vite_script', isSafe: ['html'], needsEnvironment: true)]
    public function renderViteScript(Twig $twig, string $path): string
    {
        $functions = $twig->getFunctions();
        $return = isset($functions['vite_entry_script_tags'])
            ? $functions['vite_entry_script_tags']->getCallable()($path) // @phpstan-ignore-line
            : null;

        assert(is_string($return) || null === $return);

        return $return ?? '<!--You must install vite bundle to use this function-->';
    }
}
