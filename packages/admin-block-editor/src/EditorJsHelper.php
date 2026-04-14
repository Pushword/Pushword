<?php

namespace Pushword\AdminBlockEditor;

use Exception;

use function Safe\json_decode;

final class EditorJsHelper
{
    /**
     * @return object{blocks: array<object{type: string, tunes?: object, data?: mixed, text?: string}>}|false
     */
    public static function tryToDecode(string $raw): bool|object
    {
        try {
            return self::decode($raw);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @return object{blocks: array<object{type: string, tunes?: object, data?: mixed, text?: string}>}
     */
    public static function decode(string $raw, bool $throw = true): object
    {
        if ('' === $raw || ! str_contains($raw, 'blocks')) {
            throw new Exception('JSON is empty or not contains blocks');
        }

        $data = json_decode($raw);

        if (! \is_object($data)) {
            throw new Exception('raw is not an object');
        }

        if (! property_exists($data, 'blocks') || ! \is_array($data->blocks)) {
            throw new Exception('blocks are missing');
        }

        foreach ($data->blocks as $block) {
            if (! \is_object($block)) {
                throw new Exception('Block must be an object');
            }

            if (! property_exists($block, 'type') || ! \is_string($block->type)) {
                throw new Exception('Block must have a type (string)');
            }

            if (property_exists($block, 'tunes') && ! \is_object($block->tunes)) {
                throw new Exception('if tunes is set, must be an object');
            }

            if (property_exists($block, 'text') && ! \is_string($block->text)) {
                throw new Exception('if text is set, must be a string');
            }
        }

        return $data; // @phpstan-ignore-line
    }
}
