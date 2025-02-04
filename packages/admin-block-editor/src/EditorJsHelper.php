<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use Pushword\Core\Entity\Page;

use function Safe\json_decode;

use stdClass;

final class EditorJsHelper
{
    /**
     * @return object{blocks: array<object{type: string, tunes?: object, data?: mixed, text?: string}>}
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
            if (! \is_object($block)) {
                throw new Exception('Block must be an object');
            }

            if (! property_exists($block, 'type') || ! \is_string($block->type)) {
                throw new Exception('Block must have a type (string)');
            }

            if (property_exists($block, 'tunes') && ! \is_object($block->tunes)) {
                throw new Exception('if tunes is seted, must be an object');
            }

            if (property_exists($block, 'text') && ! \is_string($block->text)) {
                throw new Exception('if text is seted, must be a string');
            }
        }

        return $data; // @phpstan-ignore-line
    }

    /**
     * @param string[] $on can contains header, paragraph...
     */
    public static function addAnchor(Page $page, string $anchor, string $regex, array $on = ['header'], ?callable $outputCallback = null): void
    {
        $mainContent = $page->getMainContent();

        try {
            $data = self::decode($mainContent);
        } catch (Exception $e) {
            return;
        }

        foreach ($data->blocks as $block) {
            if (! property_exists($block, 'tunes')) {
                continue;
            }
            if (! property_exists($block->tunes, 'anchor')) {
                continue;
            }
            if ($anchor === $block->tunes->anchor) {
                return;
            }
        }

        foreach ($on as $type) {
            foreach ($data->blocks as $block) {
                if (! property_exists($block, 'type')) {
                    continue;
                }
                if (! property_exists($block, 'data')) {
                    continue;
                }
                if (! is_object($block->data)) {
                    continue;
                }
                if (! property_exists($block->data, 'text') || ! is_string($block->data->text)) {
                    continue;
                }
                if (property_exists($block, 'tunes')
                    && property_exists($block->tunes, 'anchor') && '' !== $block->tunes->anchor) {
                    return;
                }

                if ($type === $block->type && \Safe\preg_match($regex, $block->data->text) > 0) {
                    if (null !== $outputCallback) {
                        $outputCallback($page->getHost().'/'.$page->getSlug().' : '.$block->type.' updated with anchor `'.$anchor.'`');
                    }

                    if (! property_exists($block, 'tunes')) {
                        $block->tunes = new stdClass(); // @phpstan-ignore-line
                        $block->tunes->anchor = $anchor;
                    } else {
                        assert(property_exists($block->tunes, 'anchor'));
                        $block->tunes->anchor = $anchor;
                    }

                    $page->setMainContent(\Safe\json_encode($data));

                    return;
                }
            }
        }
    }
}
