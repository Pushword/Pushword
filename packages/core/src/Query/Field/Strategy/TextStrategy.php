<?php

namespace Pushword\Core\Query\Field\Strategy;

use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;
use Pushword\Core\Query\LikePattern;

/**
 * A text column, with two ways of matching it, kept apart on purpose.
 *
 * `LIKE` passes the pattern through untouched: `slug:%tour%` is documented, and
 * an editor writing it means the wildcard. `startsWith` escapes the value and
 * carries an ESCAPE clause, because there the value is data — a slug prefix
 * holding `_` must not quietly match its neighbours.
 *
 * The same field offering both is what lets a `pages_list` search and a
 * newsletter rule name the same thing without either giving up its semantics.
 */
final readonly class TextStrategy implements FieldStrategy
{
    public function __construct(private string $column)
    {
    }

    public function operators(): array
    {
        return ['LIKE', 'NOT LIKE', '=', '!=', 'startsWith', 'notStartsWith'];
    }

    public function compile(FieldCompilation $compilation): string
    {
        $column = $compilation->column($this->column);

        return match ($compilation->operator) {
            'startsWith', 'notStartsWith' => $compilation->bind(
                LikePattern::comparison($column, 'startsWith' === $compilation->operator ? 'LIKE' : 'NOT LIKE', $compilation->parameter),
                LikePattern::escape($compilation->stringValue()).'%',
            ),
            default => $compilation->bind(
                \sprintf('%s %s :%s', $column, $compilation->operator, $compilation->parameter),
                $compilation->value,
            ),
        };
    }
}
