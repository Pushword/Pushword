<?php

namespace Pushword\Quiz\Editor;

use Pushword\AdminBlockEditor\Editor\EditorJsToolProviderInterface;
use Pushword\Core\Site\SiteRegistry;

/**
 * Contributes the Quiz block to the EditorJS toolbar. The `Quiz` JS class lives
 * in the block editor bundle (no runtime tool registration exists); this only
 * activates it. Registered only when the block editor is installed (services.php).
 */
final class QuizEditorToolProvider implements EditorJsToolProviderInterface
{
    public function __construct(private readonly SiteRegistry $apps)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getToolsConfig(string $host): array
    {
        return [
            'quiz' => [
                'name' => 'quiz',
                'className' => 'Quiz',
                // Reaches the JS tool as its `config` constructor argument.
                'config' => [
                    'conversationTypes' => $this->conversationTypes($host),
                ],
            ],
        ];
    }

    /**
     * The `cta` names a conversation form type, which is per-app config: offering
     * the real list beats asking the author to remember it. Empty when the optional
     * conversation bundle is absent, and only ever a suggestion — a type missing
     * from the map still resolves through the `App\Form\{type}` fallback, so the
     * editor must not turn this into a closed choice.
     *
     * @return string[]
     */
    private function conversationTypes(string $host): array
    {
        $app = $this->apps->get('' !== $host ? $host : null);
        if (! $app->has('conversation_form')) {
            return [];
        }

        $types = array_keys($app->getArray('conversation_form'));
        sort($types);

        return array_values(array_filter($types, static fn (mixed $type): bool => \is_string($type)));
    }
}
