<?php

namespace Pushword\Snippet\Editor;

use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;
use Pushword\Snippet\Twig\SnippetExtension;

/**
 * Contributes the Snippet block to the EditorJS toolbar, carrying the snippet
 * catalogue (components + content snippets) for the edited page's host.
 *
 * Only registered when the block editor is installed (see services.php).
 */
final readonly class SnippetEditorToolProvider implements EditorJsToolProviderInterface
{
    public function __construct(
        private SnippetExtension $snippetExtension,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getToolsConfig(string $host): array
    {
        // No anchor/class tunes: the snippet() Twig function runs before the
        // Markdown filter, so its output is a raw HTML block and a `{#id .class}`
        // attribute line in front of it is dropped by CommonMark. Offering those
        // tunes would be a control that silently does nothing.
        return [
            'snippet' => [
                'name' => 'snippet',
                'className' => 'Snippet',
                'config' => [
                    'definitions' => $this->snippetExtension->getEditorDefinitions($host),
                ],
            ],
        ];
    }
}
