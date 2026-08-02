<?php

namespace Pushword\Newsletter\Segment;

use DateTimeImmutable;
use Pushword\Newsletter\Content\PageCriteria;
use Pushword\Newsletter\Criteria\AbstractCriteria;

/**
 * The segment language: a flat list of conditions, all of which must hold.
 *
 *     [
 *       {"field": "tag",                    "op": "has",       "value": "AmTrek"},
 *       {"field": "createdAt",              "op": "olderThan", "value": "7d"},
 *       {"field": "prop.lastBoughtProduct", "op": "=",         "value": "tmb"}
 *     ]
 *
 * A rule that needs OR says so, in the shape {@see CriteriaGroup} defines:
 *
 *     {"any": [
 *       {"field": "tag", "op": "has", "value": "AmTrek"},
 *       {"field": "tag", "op": "has", "value": "AmTrek-VIP"}
 *     ]}
 *
 * Two campaigns do not replace that `any`: a contact carrying both tags would be
 * in both, and be mailed twice.
 *
 * The same list drives a campaign segment, an automation's enrollment rule and
 * its stop condition, so there is one thing to learn and one thing to test.
 *
 * Everything the shape has in common with {@see PageCriteria}
 * lives in {@see AbstractCriteria}; what is here is what makes it a *contact*
 * language — its fields, and the duration its date operators take.
 */
final class SegmentCriteria extends AbstractCriteria
{
    public const string SUBJECT = 'a contact';

    /**
     * A contact's custom property takes the duration operators too. What the site
     * knows about someone is mostly dates — `lastSeenAt`, `lastOrderAt` — and
     * they are the reason a segment exists at all; refusing them would mean
     * mirroring every one of them into a tag to filter on it.
     *
     * {@see \Pushword\Newsletter\Query\ContactPropertyStrategy} says what the
     * stored value has to look like for the comparison to mean anything.
     *
     * @var list<string>
     */
    public const array PROP_OPERATORS = [...parent::PROP_OPERATORS, ...self::DURATION_OPERATORS];

    public const array DURATION_OPERATORS = ['olderThan', 'newerThan'];

    /** Operators accepted for each plain field. `prop.*` is handled apart. */
    public const array FIELD_OPERATORS = [
        'tag' => ['has', 'hasNot'],
        'createdAt' => ['olderThan', 'newerThan'],
        'confirmedAt' => ['olderThan', 'newerThan'],
        'locale' => ['=', '!='],
    ];

    private const string DURATION_PATTERN = '/^(\d+)([mhdw])$/';

    /**
     * Resolve a duration ("7d", "90m") into the instant it points back to.
     *
     * @throws SegmentException
     */
    public static function threshold(string $duration, DateTimeImmutable $now): DateTimeImmutable
    {
        [$amount, $unit] = self::parseDuration($duration, '0');

        $minutes = $amount * match ($unit) {
            'm' => 1,
            'h' => 60,
            'd' => 60 * 24,
            'w' => 60 * 24 * 7,
            default => throw new SegmentException('Unknown duration unit "'.$unit.'".'),
        };

        return $now->modify('-'.$minutes.' minutes');
    }

    /** @return class-string<AbstractCriteria> */
    protected static function neighbour(): string
    {
        return PageCriteria::class;
    }

    /**
     * A date field takes a duration, not a date: a rule is written once and read
     * again every tick, so it says "older than 7 days", never "before July 3rd".
     *
     * @throws SegmentException
     */
    protected static function assertValue(string $field, string $op, string $value, string $path): void
    {
        if (\in_array($op, self::DURATION_OPERATORS, true)) {
            self::parseDuration($value, $path);
        }
    }

    /**
     * @return array{int, string}
     *
     * @throws SegmentException
     */
    private static function parseDuration(string $duration, string $path): array
    {
        if (1 !== preg_match(self::DURATION_PATTERN, $duration, $matches)) {
            throw new SegmentException(\sprintf('Condition #%s: "%s" is not a duration (expected e.g. 90m, 6h, 7d, 2w).', $path, $duration));
        }

        return [(int) $matches[1], $matches[2]];
    }
}
