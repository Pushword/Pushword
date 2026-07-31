<?php

namespace Pushword\Core\Query\Field\Strategy;

use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;

/**
 * A member of a JSON object column — `prop.lastBoughtProduct` and the like.
 *
 * Read through `JSON_SCALAR` rather than a substring match on the serialised
 * JSON: a substring depends on how the encoder spaced its output and only ever
 * matched string values, so `prop.count = 3` could not work at all.
 */
final readonly class JsonPropertyStrategy implements FieldStrategy
{
    public const string PREFIX = 'prop.';

    public function __construct(private string $column)
    {
    }

    public function operators(): array
    {
        return ['=', '!=', 'isSet', 'isNotSet'];
    }

    public function compile(FieldCompilation $compilation): string
    {
        $extract = \sprintf(
            "JSON_SCALAR(%s, '%s')",
            $compilation->column($this->column),
            self::path($compilation->field),
        );

        return match ($compilation->operator) {
            'isSet' => $extract.' IS NOT NULL',
            'isNotSet' => $extract.' IS NULL',
            // A missing property is not "different from x" — it is unknown. The
            // explicit IS NOT NULL keeps != from silently widening the match,
            // which is the opposite of what an absent template means.
            '!=' => $compilation->bind(
                \sprintf('(%s IS NOT NULL AND %s != :%s)', $extract, $extract, $compilation->parameter),
                $compilation->value,
            ),
            default => $compilation->bind(\sprintf('%s = :%s', $extract, $compilation->parameter), $compilation->value),
        };
    }

    /** The JSON path a `prop.<key>` field reads. */
    public static function path(string $field): string
    {
        return '$.'.substr($field, \strlen(self::PREFIX));
    }
}
