<?php

namespace Pushword\Core\Repository;

/**
 * Provides common functionality for repositories that work with tags.
 */
trait TagsRepositoryTrait
{
    /**
     * Extracts and flattens tags from query results.
     *
     * @param array{tags: string[]}[] $tagsResult Query result with tags arrays
     *
     * @return string[] Unique, flattened list of tags
     */
    protected function flattenTags(array $tagsResult): array
    {
        $allTags = [];
        foreach ($tagsResult as $entity) {
            $allTags = [...$allTags, ...$entity['tags']];
        }

        return array_values(array_unique($allTags));
    }
}
