<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use JoliTypo\Fixer;

use function Safe\json_encode;

final readonly class EditorJsPurifier
{
    public function __construct(private string $locale = 'fr_FR')
    {
    }

    public function __invoke(string $raw): string
    {
        try {
            $data = EditorJsHelper::decode($raw);
        } catch (Exception) {
            return $raw;
        }

        foreach ($data->blocks as $k => $block) {
            if (\in_array($block->type, ['header', 'paragraph'], true)) {
                if (! property_exists($data->blocks[$k], 'data') || ! \is_object($data->blocks[$k]->data)
                    || ! \is_string($text = $data->blocks[$k]->data->text ?? 0)) {
                    return $raw;
                }

                $data->blocks[$k]->data->text = self::htmlPurifier($text);
            }

            if ('list' === $block->type) {
                if (! property_exists($data->blocks[$k], 'data') || ! \is_object($data->blocks[$k]->data)
                    || ! \is_array($text = $data->blocks[$k]->data->items ?? 0)) {
                    return $raw;
                }

                /** @psalm-suppress MixedArgumentTypeCoercion */
                $data->blocks[$k]->data->items = self::htmlPurifierForList($text);
            }
        }

        return json_encode($data);
    }

    private function getFixer(): Fixer
    {
        $fixer = new Fixer(['Ellipsis', 'Dimension', 'Unit', 'Dash', 'SmartQuotes', 'FrenchNoBreakSpace', 'NoSpaceBeforeComma', 'CurlyQuote', 'Trademark']);

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
        $text = str_replace('&shy;', '', $text);
        $text = preg_replace('# </([a-z]+)>#i', '</$1> ', $text) ?? throw new Exception($text);

        if (! str_contains($text, '{{')) { // for now, we skip when there is a twig inside the text because it's convert the quote
            $text = $this->getFixer()->fix($text);
        }

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
            /** @psalm-suppress MixedArgumentTypeCoercion @phpstan-ignore-next-line*/
            $items[$k]->items = [] !== $item->items ? self::htmlPurifierForList($item->items) : [];
        }

        return $items;
    }
}
