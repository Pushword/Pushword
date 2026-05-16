<?php

namespace Pushword\Core\Service\Markdown\Extension\Processor;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedLink;

/**
 * Promotes any `[text](url)` link preceded by a `#` marker to an ObfuscatedLink.
 * Running as a post-parse processor lets CommonMark's standard link inline
 * parsing handle the anchor content (inline HTML, emphasis, autolinks, …) and
 * lets AttributesExtension handle the trailing `{.class}` / `{#id}` / `{attr=…}`
 * syntax, both of which the previous custom `#[` parser bypassed.
 */
final class ObfuscatedLinkProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $links = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Link && ! $node instanceof ObfuscatedLink) {
                $links[] = $node;
            }
        }

        foreach ($links as $link) {
            $this->maybePromote($link);
        }
    }

    private function maybePromote(Link $link): void
    {
        $previous = $link->previous();
        if (! $previous instanceof Text) {
            return;
        }

        $literal = $previous->getLiteral();
        if (! str_ends_with($literal, '#')) {
            return;
        }

        $newLiteral = substr($literal, 0, -1);
        if ('' === $newLiteral) {
            $previous->detach();
        } else {
            $previous->setLiteral($newLiteral);
        }

        $obfuscated = new ObfuscatedLink($link->getUrl(), '', $link->getTitle());

        foreach ([...$link->children()] as $child) {
            $obfuscated->appendChild($child);
        }

        $this->copyAttributes($link, $obfuscated);

        $link->replaceWith($obfuscated);
    }

    private function copyAttributes(Link $from, ObfuscatedLink $to): void
    {
        /** @var array<string, string|list<string>> $attrs */
        $attrs = $from->data->get('attributes', []);

        $class = $attrs['class'] ?? null;
        if (\is_array($class)) {
            $class = implode(' ', $class);
        }

        if (\is_string($class) && '' !== $class) {
            $to->setAttributeClass($class);
        }

        unset($attrs['class']);

        if (isset($attrs['id']) && \is_string($attrs['id'])) {
            $to->setAttributeId($attrs['id']);
            unset($attrs['id']);
        }

        /** @var array<string, string> $remaining */
        $remaining = array_map(
            static fn (string|array $v): string => \is_array($v) ? implode(' ', $v) : $v,
            $attrs,
        );

        if ([] !== $remaining) {
            $to->setAttributes($remaining);
        }
    }
}
