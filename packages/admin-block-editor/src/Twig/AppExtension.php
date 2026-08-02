<?php

namespace Pushword\AdminBlockEditor\Twig;

use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    /**
     * @param iterable<EditorJsToolProviderInterface> $toolProviders
     */
    public function __construct(
        #[AutowireIterator('pushword.editorjs_tool_provider')]
        private readonly iterable $toolProviders = [],
    ) {
    }

    /**
     * Extra EditorJS tool configs contributed by other bundles, merged into
     * `editorjsConfig.tools` by the widget template.
     *
     * @return array<string, array<string, mixed>>
     */
    #[AsTwigFunction('editorjs_extra_tools', needsEnvironment: false)]
    public function editorjsExtraTools(string $host = ''): array
    {
        $tools = [];
        foreach ($this->toolProviders as $provider) {
            $tools = [...$tools, ...$provider->getToolsConfig($host)];
        }

        return $tools;
    }
}
