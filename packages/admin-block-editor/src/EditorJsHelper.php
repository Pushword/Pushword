<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use Pushword\Core\Entity\Page;

use function Safe\json_decode;

final class EditorJsHelper
{
    /**
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
                if (! property_exists($block->data, 'text')) {
                    continue;
                }

                if ($type === $block->type && preg_match($regex, $block->data->text)) {
                    if (null !== $outputCallback) {
                        $outputCallback($page->getHost().'/'.$page->getSlug().' : '.$block->type.' updated with anchor `'.$anchor.'`');
                    }

                    if (! property_exists($block, 'tunes')) {
                        $block['tunes'] = ['anchor' => $anchor];
                    } else {
                        $block->tunes->anchor = $anchor;
                    }

                    $page->setMainContent(json_encode($data) ?? throw new Exception());

                    return;
                }
            }
        }
    }
}
