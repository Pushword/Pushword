<?php

namespace Pushword\Quiz\Editor;

use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;

/**
 * Contributes the Quiz block to the EditorJS toolbar. The `Quiz` JS class lives
 * in the block editor bundle (no runtime tool registration exists); this only
 * activates it. Registered only when the block editor is installed (services.php).
 */
final class QuizEditorToolProvider implements EditorJsToolProviderInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getToolsConfig(string $host): array
    {
        return [
            'quiz' => [
                'name' => 'quiz',
                'className' => 'Quiz',
            ],
        ];
    }
}
