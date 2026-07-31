<?php

namespace Pushword\Core\Query\Field\Strategy;

use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;

/**
 * A column that may be NULL, where being absent is a *known* state and
 * genuinely not the value being excluded.
 *
 * A page with no template uses the site's default one, and a page with no parent
 * is certainly not a child of `blog`: both belong on the `!=` side, which plain
 * SQL would drop. This is the opposite of a missing custom property, which is
 * unknown rather than different — see {@see JsonPropertyStrategy}.
 */
final readonly class OptionalColumnStrategy implements FieldStrategy
{
    public function __construct(
        private string $column,
        private ?string $alias = null,
    ) {
    }

    public function operators(): array
    {
        return ['=', '!='];
    }

    public function compile(FieldCompilation $compilation): string
    {
        $column = $compilation->column($this->column, $this->alias);

        $expression = '=' === $compilation->operator
            ? \sprintf('%s = :%s', $column, $compilation->parameter)
            : \sprintf('(%s IS NULL OR %s != :%s)', $column, $column, $compilation->parameter);

        return $compilation->bind($expression, $compilation->value);
    }
}
