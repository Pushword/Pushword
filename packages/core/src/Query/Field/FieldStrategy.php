<?php

namespace Pushword\Core\Query\Field;

/**
 * How one field turns into DQL.
 *
 * This is where a field's semantics live, and the reason there is a registry at
 * all: whether an absent value counts as different, whether a value needs LIKE
 * escaping, whether reading it means walking a join or a JSON path. Two surfaces
 * ask the same strategy, so they cannot disagree.
 */
interface FieldStrategy
{
    /**
     * The operators this field accepts. A surface that validates its vocabulary
     * reads this; the raw array form does not go through a strategy at all.
     *
     * @return list<string>
     */
    public function operators(): array;

    public function compile(FieldCompilation $compilation): string;
}
