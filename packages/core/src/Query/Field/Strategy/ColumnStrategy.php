<?php

namespace Pushword\Core\Query\Field\Strategy;

use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;

/**
 * A column compared directly. The operator is the DQL one, so this covers `=`,
 * the orderings a date needs, and `IN`.
 *
 * No NULL reasoning: for the columns that use it, a value is always there.
 * A column that may be absent wants {@see OptionalColumnStrategy}, which has to
 * decide what absence means.
 */
final readonly class ColumnStrategy implements FieldStrategy
{
    /** @param list<string> $operators */
    public function __construct(
        private string $column,
        private array $operators = ['=', '!='],
        private ?string $alias = null,
    ) {
    }

    public function operators(): array
    {
        return $this->operators;
    }

    public function compile(FieldCompilation $compilation): string
    {
        $column = $compilation->column($this->column, $this->alias);

        // Doctrine wants the placeholder inside the parentheses for IN.
        $expression = 'IN' === $compilation->operator
            ? \sprintf('%s IN (:%s)', $column, $compilation->parameter)
            : \sprintf('%s %s :%s', $column, $compilation->operator, $compilation->parameter);

        return $compilation->bind($expression, $compilation->value);
    }
}
