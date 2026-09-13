<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Tempest\Markdown\Markdown;
use Throwable;

/** Uses Tempest for the subset whose serialization matches Pushword's HTML. */
final readonly class TempestMarkdownRenderer
{
    private Markdown $markdown;

    public function __construct()
    {
        $this->markdown = new Markdown(null);
    }

    public function render(string $source): ?string
    {
        $attribute = null;
        if (1 === preg_match('/^\{id=([A-Za-z0-9_-]+)\}\n([^\r\n]+)$/D', $source, $attributes)) {
            $attribute = 'id="'.$attributes[1].'"';
            $source = $attributes[2];
        } elseif (1 === preg_match('/^([^\r\n]+) \{\.([A-Za-z0-9_-]+)\}$/D', $source, $attributes)) {
            $attribute = 'class="'.$attributes[2].'"';
            $source = $attributes[1];
        }

        $heading = preg_match('/^(#{1,6})[ \t]+([^\r\n]+)$/D', $source, $matches);
        $content = 1 === $heading ? $matches[2] : $source;
        if (! $this->isCompatibleSingleLine($content, 1 === $heading)) {
            return null;
        }

        try {
            $html = $this->markdown->parse($source)->html;
        } catch (Throwable) {
            return null;
        }

        if (1 === $heading) {
            $html = preg_replace('/^(<h[1-6]) id="[^"]*"/', '$1', $html, 1, $replacements);
            if (null === $html || 1 !== $replacements) {
                return null;
            }
        }

        if (null !== $attribute) {
            $html = preg_replace('/^<(h[1-6]|p)>/', '<$1 '.$attribute.'>', $html, 1, $replacements);
            if (null === $html || 1 !== $replacements) {
                return null;
            }
        }

        if (1 === preg_match('/(?:<strong>|<em>)[ \t]|[ \t]<\/(?:strong|em)>/', $html)) {
            return null;
        }

        // Tempest leaves quotes in text nodes unescaped, unlike CommonMark.
        $parts = preg_split('/(<[^>]+>)/', $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return null;
        }

        foreach ($parts as $index => $part) {
            if (0 === $index % 2) {
                $parts[$index] = str_replace('"', '&quot;', $part);
            }
        }

        return rtrim(implode('', $parts))."\n";
    }

    private function isCompatibleSingleLine(string $source, bool $heading): bool
    {
        if ('' === $source || $source !== ltrim($source) || ! mb_check_encoding($source, 'UTF-8')) {
            return false;
        }

        if (false !== strpbrk($source, "\r\n\t{}<>\\~&@") || 1 === preg_match('/[\x00-\x1F\x7F]/', $source)) {
            return false;
        }

        if (($heading && str_contains($source, '#')) || (! $heading && (str_starts_with($source, '#') || str_contains($source, '#['))) || str_contains($source, '![')) {
            return false;
        }

        if (str_contains($source, '***') || str_contains($source, '___') || 1 === preg_match('/[\p{L}\p{N}]_+[^_\r\n]*_+/u', $source) || 1 === preg_match("/`[^`]*'[^`]*`/", $source)) {
            return false;
        }

        if (str_contains($source, '[')) {
            $linkPattern = '/\[[^][]+\]\(([^()\r\n]*)\)/';
            preg_match_all($linkPattern, $source, $links);
            foreach ($links[1] as $destination) {
                if (1 === preg_match('/[^\x21-\x7E]|[\'\"]/', $destination)) {
                    return false;
                }
            }

            $withoutLinks = preg_replace($linkPattern, '', $source);
            if (null === $withoutLinks || false !== strpbrk($withoutLinks, '[]')) {
                return false;
            }
        }

        if (1 === preg_match('/(?:date\(|https?:\/\/\S*_\S*|\+33[ .-]?[1-9](?:[ .-]?\d{2}){4}|(?<!\d)0[1-9](?:[ .-]?\d{2}){4}(?!\d)|^[-=]{3,}\s*$)/i', $source)) {
            return false;
        }

        return $heading || 0 === preg_match('/^(?:\d+[.)]|[-+*])\s/', $source);
    }
}
