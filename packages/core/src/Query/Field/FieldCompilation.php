<?php

namespace Pushword\Core\Query\Field;

use Doctrine\ORM\QueryBuilder;

/**
 * One condition, handed to the {@see FieldStrategy} that knows what its field
 * means, with everything needed to turn it into DQL and nothing else.
 *
 * The parameter name is chosen by the compiler and reserved for this condition:
 * a strategy binds it once, whatever shape its expression takes.
 */
final readonly class FieldCompilation
{
    public function __construct(
        public QueryBuilder $queryBuilder,
        public string $alias,
        public string $field,
        public string $operator,
        public mixed $value,
        public string $parameter,
    ) {
    }

    /** The column, on the query's root alias unless the field lives on a join. */
    public function column(string $column, ?string $alias = null): string
    {
        return ($alias ?? $this->alias).'.'.$column;
    }

    /** Binds the reserved parameter and hands the expression back, so a strategy reads as one expression. */
    public function bind(string $expression, mixed $value): string
    {
        $this->queryBuilder->setParameter($this->parameter, $value);

        return $expression;
    }

    /**
     * The value as text, for the strategies that build a pattern out of it.
     *
     * A value reaches here as `mixed` — the raw array form is unvalidated by
     * design — and a strategy that concatenates it needs a string. Anything else
     * reads as the empty one rather than being cast, since casting an array
     * would be fatal and casting an object arbitrary.
     */
    public function stringValue(): string
    {
        return \is_string($this->value) ? $this->value : '';
    }
}
