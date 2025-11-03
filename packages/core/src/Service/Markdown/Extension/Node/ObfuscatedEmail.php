<?php

namespace Pushword\Core\Service\Markdown\Extension\Node;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Représente un email obfusqué.
 */
class ObfuscatedEmail extends Link
{
    public function __construct(string $url, ?string $label = null)
    {
        parent::__construct($url, $label, null);
    }
}
