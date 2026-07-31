<?php

namespace Pushword\Core\Query;

/**
 * One comparison, in the representation both search surfaces parse into.
 *
 * `field` is a name, not a column: what it means is a field registry's business.
 * Nothing here knows which entity is being filtered, which is what lets a rule
 * over pages and a rule over contacts share a parser and a tree walk.
 */
final readonly class Condition
{
    /**
     * @param string      $operator  a DQL comparison — `=`, `LIKE`, `IN`, `IS`…
     * @param string|null $keyPrefix the alias the field hangs off, when it is not
     *                               the query's root one — `parent.` reaches the
     *                               join `buildPageQuery()` already declares
     */
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value = null,
        public ?string $keyPrefix = null,
    ) {
    }
}
