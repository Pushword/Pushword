<?php

namespace Pushword\Core\Query;

/**
 * Renders a {@see Group}/{@see Condition} tree back into the nested array form
 * {@see \Pushword\Core\Repository\FilterWhereParser} reads.
 *
 * A bridge, and a temporary one: it exists so the parser can be replaced without
 * touching the compiler, and it goes away once every caller compiles the tree
 * directly. It upholds the three invariants that compiler relies on and never
 * checks — a level is homogeneous, a group is never empty, and a group never
 * starts with a marker — which is why the tree is rendered here rather than
 * assembled by hand.
 */
final class LegacyArrayRenderer
{
    /** @return array<mixed> */
    public function render(Group|Condition $node): array
    {
        // A lone condition still has to arrive as a list of one: the compiler
        // tells a group from a condition by whether the first element is an array.
        return $node instanceof Group ? $this->group($node) : [$this->condition($node)];
    }

    /** @return array<mixed> */
    private function group(Group $group): array
    {
        $rendered = [];

        foreach ($group->children as $index => $child) {
            if ($index > 0) {
                $rendered[] = $group->conjunction->value;
            }

            $rendered[] = $child instanceof Group ? $this->group($child) : $this->condition($child);
        }

        return $rendered;
    }

    /** @return array<mixed> */
    private function condition(Condition $condition): array
    {
        if (null === $condition->keyPrefix) {
            return [$condition->field, $condition->operator, $condition->value];
        }

        return [
            'key' => $condition->field,
            'operator' => $condition->operator,
            'value' => $condition->value,
            'key_prefix' => $condition->keyPrefix,
        ];
    }
}
