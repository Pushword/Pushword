<?php

declare(strict_types=1);

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
        return $this->prepare($body, $edits)['content'];
    }

    /**
     * @param list<array<string, mixed>> $edits
     *
     * @return array{content: string, replacements: list<array{find: string, replace: string}>}
     */
    public function prepare(string $body, array $edits): array
    {
        $replacements = [];
        foreach ($edits as $index => $edit) {
            $find = \is_string($edit['find'] ?? null) ? $edit['find'] : '';
            $replace = \is_string($edit['replace'] ?? null) ? $edit['replace'] : '';
            $matches = '' === $find ? 0 : substr_count($body, $find);

            if (0 === $matches) {
                throw new BodyPatchException($index, 'not_found', 0);
            }

            if ($matches > 1 && true !== ($edit['replaceAll'] ?? false)) {
                throw new BodyPatchException($index, 'ambiguous', $matches);
            }

            $body = str_replace($find, $replace, $body);
            $replacements[] = ['find' => $find, 'replace' => $replace];
        }

        return ['content' => $body, 'replacements' => $replacements];
    }
}
