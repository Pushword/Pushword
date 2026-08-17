<?php

namespace Pushword\Core\Query\Field\Strategy;

use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;
use Pushword\Core\Query\LikePattern;

/**
 * Membership of a JSON array column — the shape both a page's and a contact's
 * tags are stored in.
 *
 * Matching on the quoted form (`"AmTrek"`) keeps a shorter tag from matching a
 * longer one that starts with it, and {@see LikePattern} keeps a tag holding `_`
 * or `%` from reaching its neighbours.
 */
final readonly class JsonArrayStrategy implements FieldStrategy
{
    public function __construct(private string $column)
    {
    }

    public function operators(): array
    {
        return ['has', 'hasNot'];
    }

    public function compile(FieldCompilation $compilation): string
    {
        return $compilation->bind(
            LikePattern::comparison(
                'JSON_TEXT('.$compilation->column($this->column).')',
                'has' === $compilation->operator ? 'LIKE' : 'NOT LIKE',
                $compilation->parameter,
            ),
            '%"'.LikePattern::escape($compilation->stringValue()).'"%',
        );
    }
}
