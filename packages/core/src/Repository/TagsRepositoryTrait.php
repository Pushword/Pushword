<?php

declare(strict_types=1);

namespace Pushword\Core\Repository;

/**
 * Provides common functionality for repositories that work with tags.
 */
trait TagsRepositoryTrait
{
    /**
     * Extracts and flattens tags from query results.
     *
     * Deduplicates through array keys rather than accumulating a flat list:
     * on a real media table (11.6k rows, 4.2k distinct tags) rebuilding the
     * accumulator per row cost 500ms against 77ms of DQL. The value, not the
     * key, is returned, so a numeric-looking tag ("2024") stays the string it
     * was instead of being coerced by PHP's array-key rules.
     *
     * @param array{tags: string[]}[] $tagsResult Query result with tags arrays
     *
     * @return string[] Unique, flattened list of tags, in first-seen order
     */
    protected function flattenTags(array $tagsResult): array
    {
        $allTags = [];
        foreach ($tagsResult as $entity) {
            foreach ($entity['tags'] as $tag) {
                $allTags[$tag] ??= $tag;
            }
        }

        return array_values($allTags);
    }
}
