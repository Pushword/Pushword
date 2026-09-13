<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Support;

use DOMComment;
use DOMElement;
use DOMNode;
use DOMText;
use Masterminds\HTML5;

/** Compare rendered HTML while ignoring attribute order and structural table spacing. */
final class HtmlEquivalence
{
    /** @return array<mixed> */
    public static function structure(string $html): array
    {
        return self::node(new HTML5()->loadHTMLFragment($html));
    }

    /** @return array<mixed> */
    private static function node(DOMNode $node): array
    {
        if ($node instanceof DOMText) {
            return ['text', $node->data];
        }

        if ($node instanceof DOMComment) {
            return ['comment', $node->data];
        }

        $attributes = [];
        if ($node instanceof DOMElement) {
            foreach ($node->attributes as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue;
            }

            ksort($attributes);
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText
                && $node instanceof DOMElement
                && \in_array($node->tagName, ['table', 'thead', 'tbody', 'tfoot', 'tr'], true)
                && '' === trim($child->data)) {
                continue;
            }

            $children[] = self::node($child);
        }

        return [$node->nodeName, $attributes, $children];
    }
}
