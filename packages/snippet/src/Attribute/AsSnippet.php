<?php

namespace Pushword\Snippet\Attribute;

use Attribute;

/**
 * Marks a class as a dev-registered component snippet, renderable through the
 * `snippet('name', {...params})` Twig function and exposed in the block editor.
 *
 * The class must implement {@see \Pushword\Snippet\Component\SnippetComponentInterface}.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsSnippet
{
    public function __construct(
        public string $name,
        public string $template,
        public ?string $label = null,
    ) {
    }
}
