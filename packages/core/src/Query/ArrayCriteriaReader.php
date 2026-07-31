<?php

namespace Pushword\Core\Query;

use InvalidArgumentException;

/**
 * Reads the raw array form into the tree.
 *
 *     ['title', 'LIKE', '%this%']
 *     [['title', 'LIKE', '%this%'], 'OR', ['title', 'LIKE', '%that%']]
 *     [[['h1', 'LIKE', '%a%'], 'OR', ['h1', 'LIKE', '%b%']], ['slug', 'LIKE', 'c']]
 *
 * A group is told from a condition by its first element being an array, and the
 * conjunction by whether an `'OR'` marker sits between the children — which is
 * why a level holding both markers is not a mixed expression but an all-OR one,
 * and why nothing here validates a field name. This is the escape hatch: callers
 * pass conditions the search vocabulary has no word for, and that is the point.
 */
final class ArrayCriteriaReader
{
    /**
     * @param array<mixed> $where
     *
     * @return Group|Condition|null null when there is nothing to filter on
     */
    public function read(array $where): Group|Condition|null
    {
        if ([] === $where) {
            return null;
        }

        // A lone condition — `['title', 'LIKE', '%x%']` or its named form.
        if (! isset($where[0]) || ! \is_array($where[0])) {
            return $this->condition($where);
        }

        return $this->group($where);
    }

    /**
     * @param array<mixed> $where
     */
    private function group(array $where): Group|Condition
    {
        $children = [];

        foreach ($where as $row) {
            if (\in_array($row, ['OR', 'AND'], true)) {
                continue;
            }

            if (! \is_array($row)) {
                throw new InvalidArgumentException(\sprintf('A criteria row must be an array or a marker, got "%s".', get_debug_type($row)));
            }

            $children[] = $this->isGroup($row) ? $this->group($row) : $this->condition($row);
        }

        if ([] === $children) {
            throw new InvalidArgumentException('An empty group would compile to "()"; write the condition or leave it out.');
        }

        return 1 === \count($children)
            ? $children[0]
            : new Group(\in_array('OR', $where, true) ? Conjunction::Any : Conjunction::All, $children);
    }

    /** @param array<mixed> $row */
    private function isGroup(array $row): bool
    {
        if ([] === $row) {
            throw new InvalidArgumentException('An empty group would compile to "()"; write the condition or leave it out.');
        }

        return \is_array(array_values($row)[0]);
    }

    /** @param array<mixed> $row */
    private function condition(array $row): Condition
    {
        $field = $row['key'] ?? $row[0] ?? throw new InvalidArgumentException('A criteria row needs a field name.');
        $operator = $row['operator'] ?? $row[1] ?? throw new InvalidArgumentException('A criteria row needs an operator.');

        return new Condition(
            \is_string($field) ? $field : throw new InvalidArgumentException('A field name must be a string.'),
            \is_string($operator) ? $operator : throw new InvalidArgumentException('An operator must be a string.'),
            $row['value'] ?? $row[2] ?? null,
            // Index 4, not 3: the positional form has always skipped one.
            $this->stringOrNull($row['key_prefix'] ?? $row[4] ?? null),
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
