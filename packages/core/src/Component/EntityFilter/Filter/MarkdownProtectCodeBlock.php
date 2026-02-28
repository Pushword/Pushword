<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

final class MarkdownProtectCodeBlock
{
    /**
     * @var array<string, string>
     */
    private array $codeBlocks = [];

    public function protect(mixed $text): string
    {
        $i = 0;
        $codeBlocks = [];
        $replacer = static function (array $matches) use (&$codeBlocks, &$i): string {
            $placeholder = '___CODE_BLOCK_PLACEHOLDER_'.($i++).'___';
            $codeBlocks[$placeholder] = trim($matches[0]); // @phpstan-ignore argument.type

            return $placeholder."\n\n";
        };

        $patterns = [
            '/^```(.*?)```(\n\n|$)/ms',
            '/^<pre(?:\s[^>]*)?>.*?<\/pre>(\n\n|$)/ms',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace_callback($pattern, $replacer, $text) ?? $text; // @phpstan-ignore argument.type
        }

        $this->codeBlocks = $codeBlocks;

        assert(is_string($text));

        return $text;
    }

    /**
     * @param string[] $textParts
     *
     * @return string[]
     */
    public function restore(array $textParts): array
    {
        if ([] === $this->codeBlocks) {
            return $textParts;
        }

        foreach ($this->codeBlocks as $placeholder => $codeBlock) {
            $textParts = str_replace($placeholder, $codeBlock, $textParts);
        }

        return array_map(function (string $textPart): string {
            if (str_starts_with($textPart, '___CODE_BLOCK_PLACEHOLDER_')) {
                return $this->codeBlocks[$textPart] ?? $textPart;
            }

            return $textPart;
        }, $textParts);
    }
}
