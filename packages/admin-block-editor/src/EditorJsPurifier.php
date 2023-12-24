<?php

namespace Pushword\AdminBlockEditor;

use JoliTypo\Fixer;

final class EditorJsPurifier
{
    public function __construct(private readonly string $locale = 'fr_FR')
    {
    }

    public function __invoke(string $raw): string
    {
        try {
            $data = EditorJsHelper::decode($raw);
        } catch (\Exception) {
            return $raw;
        }

        foreach ($data->blocks as $k => $block) {
            if (\in_array($block->type, ['header', 'paragraph'], true)) {
                if (! \is_string($data->blocks[$k]->data->text ?? 0)) {
                    return $raw;
                }

                $data->blocks[$k]->data->text = self::htmlPurifier($data->blocks[$k]->data->text);
            }

            if ('list' === $block->type) {
                if (! \is_array($data->blocks[$k]->data->items ?? 0)) {
                    return $raw;
                }

                $data->blocks[$k]->data->items = self::htmlPurifierForList($data->blocks[$k]->data->items);
            }
        }

        return \Safe\json_encode($data);
    }

    private function getFixer(): Fixer
    {
        $fixer = new Fixer(['Ellipsis', 'Dimension', 'Unit', 'Dash', 'SmartQuotes', 'FrenchNoBreakSpace', 'NoSpaceBeforeComma', 'CurlyQuote', 'Hyphen', 'Trademark']);

        if (\in_array($this->locale, ['fr', 'fr_FR'], true)) {
            $fixer->setLocale('fr_FR');
        } else {
            $fixer->setLocale('en_GB');
        }

        return $fixer;
    }

    public function htmlPurifier(string $text): string
    {
        $text = str_replace("\u{a0}", ' ', $text);
        $text = preg_replace('# </([a-z]+)>#i', '</$1> ', $text) ?? throw new \Exception($text);
        // $text = $this->getFixer()->fix($text);

        return trim($text);
    }

    /**
     * @param array<array-key, object{items: array<mixed>, content: string}> $items
     *
     * @return array<array-key, object{items: array<mixed>, content: string}>
     */
    private function htmlPurifierForList(array $items): array
    {
        foreach ($items as $k => $item) {
            $items[$k]->content = self::htmlPurifier($item->content); // @phpstan-ignore-line
            $items[$k]->items = [] !== $item->items ? self::htmlPurifierForList($item->items) : []; // @phpstan-ignore-line
        }

        return $items;
    }
}
