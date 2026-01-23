<?php

namespace Pushword\Core\Extension\Markdown\Util;

use Stringable;

/**
 * Wrapper simple pour du HTML brut à retourner depuis un renderer.
 */
final readonly class RawHtml implements Stringable
{
    public function __construct(
        private string $html
    ) {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
