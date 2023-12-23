<?php

namespace Pushword\AdminBlockEditor;

final class EditorJsHelper
{
    /**
     * @psalm-suppress MoreSpecificReturnType
     *  @psalm-suppress LessSpecificReturnStatement
     *
     * @return object{blocks: array<object{type: string, data: object}>}
     */
    public static function decode(string $raw): object
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
}
