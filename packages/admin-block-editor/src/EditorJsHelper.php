<?php

namespace Pushword\AdminBlockEditor;

class EditorJsHelper
{
    /**
     * @psalm-suppress MoreSpecificReturnType
     *  @psalm-suppress LessSpecificReturnStatement
     *
     * @return object{blocks: array<object{type: string, data: object}>}
     */
    public static function jsonDecode(string $raw): object
    {
        if ('' === $raw) {
            throw new \Exception('JSON is empty');
        }

        $data = \Safe\json_decode($raw);

        if (! \is_object($data)) {
            throw new \Exception('raw is not an object');
        }

        if (! property_exists($data, 'blocks') || ! \is_array($data->blocks)) {
            throw new \Exception('blocks are missing');
        }

        foreach ($data->blocks as $block) {
            if (! property_exists($block, 'type') || ! \is_string($block->type)) {
                throw new \Exception('Block must have a type (string)');
            }

            if (! property_exists($block, 'data') || ! \is_object($block->data)) {
                throw new \Exception('Block must have a data (object)');
            }
        }

        return $data; // @phpstan-ignore-line
    }

    public static function htmlPurifier(string $text): string
    {
        $text = str_replace("\u{a0}", ' ', $text);
        $text = preg_replace('# </([a-z]+)>#i', '</$1> ', $text) ?? throw new \Exception($text);

        return trim($text);
    }

    /**
     * @param array<array-key, object{items: array<mixed>, content: string}> $items
     *
     * @return array<array-key, object{items: array<mixed>, content: string}>
     */
    public static function htmlPurifierForList(array $items): array
    {
        foreach ($items as $k => $item) {
            $items[$k]->content = self::htmlPurifier($item->content); // @phpstan-ignore-line
            $items[$k]->items = [] !== $item->items ? self::htmlPurifierForList($item->items) : []; // @phpstan-ignore-line
        }

        return $items;
    }

    public static function purify(string $raw): string
    {
        try {
            $data = self::jsonDecode($raw);
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
}
