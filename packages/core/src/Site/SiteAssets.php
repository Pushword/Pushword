<?php

namespace Pushword\Core\Site;

final readonly class SiteAssets
{
    /**
     * @param string[] $javascripts
     * @param string[] $stylesheets
     * @param string[] $viteJavascripts
     * @param string[] $viteStylesheets
     */
    public function __construct(
        public array $javascripts = [],
        public array $stylesheets = [],
        public array $viteJavascripts = [],
        public array $viteStylesheets = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var string[] $js */
        $js = $data['javascripts'] ?? [];
        /** @var string[] $css */
        $css = $data['stylesheets'] ?? [];
        /** @var string[] $viteJs */
        $viteJs = $data['vite_javascripts'] ?? [];
        /** @var string[] $viteCss */
        $viteCss = $data['vite_stylesheets'] ?? [];

        return new self(
            javascripts: $js,
            stylesheets: $css,
            viteJavascripts: $viteJs,
            viteStylesheets: $viteCss,
        );
    }
}
