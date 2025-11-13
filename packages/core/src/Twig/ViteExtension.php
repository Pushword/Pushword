<?php

namespace Pushword\Core\Twig;

use Pentatrion\ViteBundle\Service\EntrypointsLookupCollection;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final readonly class ViteExtension
{
    public function __construct(
        private ?EntrypointsLookupCollection $entrypointsLookupCollection = null,
    ) {
    }

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

    /**
     * @return array<string>
     */
    #[AsTwigFunction('vite_js_files', needsEnvironment: false)]
    public function getEntry(string $entryName, ?string $configName = null): array
    {
        if (null === $this->entrypointsLookupCollection) {
            return [];
        }

        return $this->entrypointsLookupCollection->getEntrypointsLookup($configName)->getJSFiles($entryName);
    }
}
