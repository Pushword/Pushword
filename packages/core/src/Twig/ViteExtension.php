<?php

namespace Pushword\Core\Twig;

use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final class ViteExtension
{
    #[AsTwigFunction('vite_style', isSafe: ['html'], needsEnvironment: true)]
    public function renderViteStylesheet(Twig $twig, string $path): string
    {
        // TODO : to test else use
        // return $twig->createTemplate('{{ vite_entry_link_tags("'.$path.'") }}')->render();
        // VS.
        // $functions['vite_entry_link_tags']->getCallable()($path)
        $functions = $twig->getFunctions();
        $return = isset($functions['vite_entry_link_tags'])
            ? $twig->createTemplate('{{ vite_entry_link_tags("'.$path.'") }}')->render()
            : null;

        return $return ?? '<!--You must install vite bundle to use this function-->';
    }

    #[AsTwigFunction('vite_script', isSafe: ['html'], needsEnvironment: true)]
    public function renderViteScript(Twig $twig, string $path): string
    {
        $functions = $twig->getFunctions();
        $return = isset($functions['vite_entry_script_tags'])
            ? $twig->createTemplate('{{ vite_entry_script_tags("'.$path.'") }}')->render()
            : null;

        return $return ?? '<!--You must install vite bundle to use this function-->';
    }
}
