<?php

namespace Pushword\Newsletter\Query;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Pushword\Core\Query\Field\FieldCompilation;
use Pushword\Core\Query\Field\FieldStrategy;
use Pushword\Core\Query\Field\Strategy\JsonPropertyStrategy;
use Pushword\Newsletter\Segment\SegmentCriteria;

/**
 * A contact's custom property — `prop.lastSeenAt` — with the two duration
 * operators a segment speaks on top of the plain ones.
 *
 * The comparison is lexical, not numeric: an ISO-8601 instant sorts as text in
 * the order it sorts in time, so `<=` on the stored string is `<=` on the date,
 * with no cast and no declared type. What that buys is also what it demands —
 * every value of the property has to be written the same way, and in the same
 * offset. `2026-08-02T08:00:00Z` and `2026-08-02T10:00:00+02:00` are the same
 * instant and do not compare as one, so a site that mixes them is comparing two
 * calendars. Write UTC, always, and the operator means what it says.
 *
 * Everything that is not a duration is {@see JsonPropertyStrategy}'s, unchanged:
 * one field cannot read one way under `=` and another under `olderThan`.
 */
final readonly class ContactPropertyStrategy implements FieldStrategy
{
    private const array DURATION_OPERATORS = ['olderThan', 'newerThan'];

    private JsonPropertyStrategy $plain;

    public function __construct(
        private string $column,
        private DateTimeImmutable $now,
    ) {
        $this->plain = new JsonPropertyStrategy($column);
    }

    public function operators(): array
    {
        return [...$this->plain->operators(), ...self::DURATION_OPERATORS];
    }

    public function compile(FieldCompilation $compilation): string
    {
        if (! \in_array($compilation->operator, self::DURATION_OPERATORS, true)) {
            return $this->plain->compile($compilation);
        }

        $key = substr($compilation->field, \strlen(JsonPropertyStrategy::PREFIX));
        if (1 !== preg_match(JsonPropertyStrategy::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(\sprintf('Invalid property name in `%s`.', $compilation->field));
        }

        return $compilation->bind(
            \sprintf(
                "JSON_SCALAR(%s, '%s') %s :%s",
                $compilation->column($this->column),
                JsonPropertyStrategy::path($compilation->field),
                'olderThan' === $compilation->operator ? '<=' : '>=',
                $compilation->parameter,
            ),
            // A property nobody ever wrote is NULL, and NULL compares to nothing
            // — a contact with no `lastSeenAt` is absent from both sides of the
            // operator rather than counting as infinitely old. Saying otherwise
            // is `{"any": [older-than, isNotSet]}`, which is the caller's to
            // write and not this strategy's to assume.
            SegmentCriteria::threshold($compilation->stringValue(), $this->now)->format(DateTimeInterface::ATOM),
        );
    }
}
