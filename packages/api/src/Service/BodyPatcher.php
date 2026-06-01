<?php

namespace Pushword\Api\Service;

/**
 * Applies anchored find/replace edits to a Markdown body.
 *
 * Each edit's `find` must match exactly once, unless `replaceAll` is true. An
 * ambiguous (>1) or missing (0) match raises a {@see BodyPatchException} instead
 * of editing the wrong spot — ambiguity becomes a loud error, never a silent
 * wrong modification. Edits apply sequentially in memory (an edit may only become
 * matchable after a previous one), and the caller persists nothing if any fails.
 */
final readonly class BodyPatcher
{
    /**
     * @param list<array<string, mixed>> $edits
     */
    public function apply(string $body, array $edits): string
    {
        foreach ($edits as $index => $edit) {
            $body = $this->applyOne($body, $edit, $index);
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $edit
     */
    private function applyOne(string $body, array $edit, int $index): string
    {
        $find = \is_string($edit['find'] ?? null) ? $edit['find'] : '';
        $replace = \is_string($edit['replace'] ?? null) ? $edit['replace'] : '';
        $replaceAll = true === ($edit['replaceAll'] ?? false);

        $matches = '' === $find ? 0 : substr_count($body, $find);

        if (0 === $matches) {
            throw new BodyPatchException($index, 'not_found', 0);
        }

        if ($matches > 1 && ! $replaceAll) {
            throw new BodyPatchException($index, 'ambiguous', $matches);
        }

        return str_replace($find, $replace, $body);
    }
}
