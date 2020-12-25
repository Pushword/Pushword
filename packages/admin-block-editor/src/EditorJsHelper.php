<?php

namespace Pushword\AdminBlockEditor;

use Exception;

use function Safe\json_decode;

final class EditorJsHelper
{
    /**
     * @psalm-suppress MoreSpecificReturnType
     *  @psalm-suppress LessSpecificReturnStatement
     *
     * @return object{blocks: array<object{type: string}>}
     */
    public static function decode(string $raw): object
    {
        if ('' === $raw) {
            throw new Exception('JSON is empty');
        }

        $data = json_decode($raw);

        if (! \is_object($data)) {
            throw new Exception('raw is not an object');
        }

        if (! property_exists($data, 'blocks') || ! \is_array($data->blocks)) {
            throw new Exception('blocks are missing');
        }

        foreach ($data->blocks as $block) {
            if (! \is_object($block) || ! property_exists($block, 'type') || ! \is_string($block->type)) {
                throw new Exception('Block must have a type (string)');
            }
        }

        return $data; // @phpstan-ignore-line
    }
}
