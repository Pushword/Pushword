<?php

namespace Pushword\Snippet\Component;

interface SnippetComponentInterface
{
    /**
     * Declares the editable parameters. Drives both rendering and the block-editor
     * form. Each entry maps a param name to a definition:
     *   ['type' => 'string'|'text'|'bool'|'select'|'media'|'collection', ...].
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchema(): array;

    /**
     * Normalises / completes the params received from content before rendering
     * (apply defaults, cast types…). Return value is passed to the template.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function prepareParams(array $params): array;
}
