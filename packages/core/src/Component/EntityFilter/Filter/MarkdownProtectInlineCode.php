<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

final class MarkdownProtectInlineCode
{
    /**
     * @var array<string, string>
     */
    private array $inlineCodes = [];

    public function protect(mixed $text): string
    {
        $i = 0;
        $inlineCodes = [];
        $text = preg_replace_callback(
            '/(`+)([^\n]*?)\1/', // normally inline code permits new line
            static function (array $matches) use (&$inlineCodes, &$i): string {
                $placeholder = '___INLINE_CODE_PLACEHOLDER_'.($i++).'___';
                $inlineCodes[$placeholder] = $matches[0];

                return $placeholder;
            },
            $text // @phpstan-ignore-line
        ) ?? $text;

        $this->inlineCodes = $inlineCodes;

        assert(is_string($text));

        return $text;
    }

    public function restore(string $text): string
    {
        if ([] === $this->inlineCodes) {
            return $text;
        }

        foreach ($this->inlineCodes as $placeholder => $inlineCode) {
            $text = str_replace($placeholder, $inlineCode, $text);
        }

        return $text;
    }
}
