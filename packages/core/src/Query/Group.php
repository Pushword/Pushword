<?php

namespace Pushword\Core\Query;

/**
 * A node of the search tree: children joined by one conjunction.
 *
 * Nesting is the whole point. A `pages_list` search has produced two-level trees
 * since long before it had a parser — `title:foo AND children` is
 * `(h1 OR title) AND parentPage` — and a group that can hold a group is what
 * lets a surface say so explicitly instead of stumbling into it.
 */
final readonly class Group
{
    /** @param list<Group|Condition> $children */
    public function __construct(
        public Conjunction $conjunction,
        public array $children,
    ) {
    }
}
