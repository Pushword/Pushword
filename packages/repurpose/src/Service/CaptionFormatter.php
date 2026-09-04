<?php

namespace Pushword\Repurpose\Service;

/**
 * Formats the text exported or sent to a social network from a caption and its
 * separately stored hashtags.
 */
final class CaptionFormatter
{
    /**
     * @param list<string> $hashtags
     */
    public static function format(?string $caption, array $hashtags): string
    {
        $text = trim($caption ?? '');
        $hashtags = array_map(static fn (string $hashtag): string => '#'.ltrim($hashtag, '#'), $hashtags);

        if ([] === $hashtags) {
            return $text;
        }

        return $text.('' === $text ? '' : "\n\n").implode(' ', $hashtags);
    }
}
