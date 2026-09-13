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
        $heading = preg_match('/^(#{1,6})[ \t]+([^\r\n]+)$/D', $source, $matches);
        $content = 1 === $heading ? $matches[2] : $source;
        if (! $this->isPlainText($content)) {
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

        // Eligible text cannot create HTML attributes after the heading ID is removed.
        return str_replace('"', '&quot;', rtrim($html))."\n";
    }

    private function isPlainText(string $source): bool
    {
        if ('' === $source || $source !== ltrim($source) || ! mb_check_encoding($source, 'UTF-8')) {
            return false;
        }

        if (false !== strpbrk($source, "\r\n\t#*`[]{}<>!|_\\~&@") || 1 === preg_match('/[\x00-\x1F\x7F]/', $source)) {
            return false;
        }

        return 0 === preg_match('/(?:date\(|:\/\/|www\.|(?<!\d)0[1-9](?:[ .-]?\d{2}){4}(?!\d)|^(?:\d+[.)]|[-+])\s|^[-=]{3,}\s*$)/i', $source);
    }
}
