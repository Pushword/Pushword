<?php

namespace Pushword\Newsletter\Tests\Segment;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Group;
use Pushword\Newsletter\Segment\CriteriaGroup;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;

final class SegmentCriteriaTest extends TestCase
{
    /** A compact rendering of the tree: `AND(tag,OR(locale,locale))`. */
    private function shape(Group|Condition|null $node): string
    {
        if (null === $node) {
            return 'nothing';
        }

        if ($node instanceof Condition) {
            return $node->field;
        }

        return $node->conjunction->value.'('.implode(',', array_map($this->shape(...), $node->children)).')';
    }

    public function testNormalizeReadsTheThreeSupportedShapes(): void
    {
        $rule = SegmentCriteria::normalize([
            ['field' => ' tag ', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'createdAt', 'op' => 'olderThan', 'value' => '7d'],
            ['field' => 'prop.lastBoughtProduct', 'op' => '=', 'value' => 'tmb'],
        ]);

        self::assertSame('AND(tag,createdAt,prop.lastBoughtProduct)', $this->shape($rule));
        self::assertInstanceOf(Group::class, $rule);
        $first = $rule->children[0];
        self::assertInstanceOf(Condition::class, $first);
        self::assertSame('AmTrek', $first->value);
    }

    /** A bare list is ANDed; `any` is the one thing a rule has to say out loud. */
    public function testAGroupCarriesItsOperator(): void
    {
        $conditions = [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek-VIP'],
        ];

        self::assertSame('OR(tag,tag)', $this->shape(SegmentCriteria::normalize(['any' => $conditions])));
        self::assertSame('AND(tag,tag)', $this->shape(SegmentCriteria::normalize(['all' => $conditions])));
        self::assertSame('AND(tag,tag)', $this->shape(SegmentCriteria::normalize($conditions)));
    }

    /**
     * A contact side gets the same ceiling as the page side: either of two tags,
     * but only among the customers who bought something.
     */
    public function testAChildMayBeAGroupOfItsOwn(): void
    {
        $rule = SegmentCriteria::normalize([
            ['field' => 'prop.lastBoughtProduct', 'op' => 'isSet'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek-VIP'],
            ]],
        ]);

        self::assertSame('AND(prop.lastBoughtProduct,OR(tag,tag))', $this->shape($rule));
    }

    /**
     * The textarea's round trip must not lose the operator: an `any` coming back
     * as a bare list would silently become an `all`, and a segment written to
     * reach two groups would reach only their intersection.
     */
    public function testTheJsonRoundTripKeepsEveryOperator(): void
    {
        $rule = ['any' => [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]];
        self::assertSame($rule, SegmentCriteria::fromJson(SegmentCriteria::toJson($rule)));

        $nested = [
            ['field' => 'prop.lastBoughtProduct', 'op' => 'isSet', 'value' => ''],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek-VIP'],
            ]],
        ];
        self::assertSame($nested, SegmentCriteria::fromJson(SegmentCriteria::toJson($nested)));
    }

    public function testAGroupMustHoldAList(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/must hold a list of conditions/');

        SegmentCriteria::normalize(['any' => 'AmTrek']);
    }

    public function testARuleCannotBeBothAnyAndAll(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/not both/');

        SegmentCriteria::normalize(['any' => [], 'all' => []]);
    }

    public function testValuelessOperatorsDropTheirValue(): void
    {
        $rule = SegmentCriteria::normalize([['field' => 'prop.x', 'op' => 'isSet', 'value' => 'ignored']]);

        self::assertInstanceOf(Group::class, $rule);
        $condition = $rule->children[0];
        self::assertInstanceOf(Condition::class, $condition);
        self::assertSame('', $condition->value);
    }

    public function testAnEmptyListFiltersNothing(): void
    {
        self::assertNull(SegmentCriteria::normalize([]));
    }

    public function testUnknownFieldIsRejected(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/unknown field "nickname"/');

        SegmentCriteria::normalize([['field' => 'nickname', 'op' => '=', 'value' => 'Robin']]);
    }

    /** The page language is next door, and its fields say so rather than reading as unknown. */
    public function testAPageFieldSaysSo(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/"ancestor" filters a page, not a contact/');

        SegmentCriteria::normalize([['field' => 'ancestor', 'op' => '=', 'value' => 'blog']]);
    }

    public function testOperatorMustApplyToTheField(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/does not apply to "tag"/');

        SegmentCriteria::normalize([['field' => 'tag', 'op' => 'olderThan', 'value' => '7d']]);
    }

    public function testAPropertyFieldNeedsAName(): void
    {
        $this->expectException(SegmentException::class);

        SegmentCriteria::normalize([['field' => 'prop.', 'op' => '=', 'value' => 'x']]);
    }

    public function testAValueIsRequiredUnlessTheOperatorIsValueless(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/needs a value/');

        SegmentCriteria::normalize([['field' => 'tag', 'op' => 'has']]);
    }

    public function testMalformedDurationIsRejected(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/is not a duration/');

        SegmentCriteria::normalize([['field' => 'createdAt', 'op' => 'olderThan', 'value' => '7 days']]);
    }

    public function testConditionMustBeAnObject(): void
    {
        $this->expectException(SegmentException::class);

        SegmentCriteria::normalize(['tag']);
    }

    public function testThresholdWalksBackByTheDuration(): void
    {
        $now = new DateTimeImmutable('2026-07-27 12:00:00');

        self::assertSame('2026-07-27 10:30:00', SegmentCriteria::threshold('90m', $now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-27 06:00:00', SegmentCriteria::threshold('6h', $now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-20 12:00:00', SegmentCriteria::threshold('7d', $now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-13 12:00:00', SegmentCriteria::threshold('2w', $now)->format('Y-m-d H:i:s'));
    }

    public function testJsonRoundTrip(): void
    {
        $criteria = [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']];

        self::assertSame($criteria, SegmentCriteria::fromJson(SegmentCriteria::toJson($criteria)));
    }

    public function testEmptyJsonIsAnEmptySegment(): void
    {
        self::assertSame('', SegmentCriteria::toJson([]));
        self::assertSame([], SegmentCriteria::fromJson(''));
        self::assertSame([], SegmentCriteria::fromJson(null));
    }

    public function testMalformedJsonIsRejected(): void
    {
        $this->expectException(SegmentException::class);

        SegmentCriteria::fromJson('{not json');
    }

    /**
     * A page rule may be written as a `pages_list` search; a segment may not.
     * Its operators — `olderThan 7d`, `isSet` — have no spelling in a
     * `field:value` grammar, and inventing one would be a third language rather
     * than a shared one. It says so instead of reading the string as a tag.
     */
    public function testASegmentCannotBeWrittenAsASearchString(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/must be a JSON list/');

        SegmentCriteria::fromJson('tag:AmTrek AND locale:fr');
    }

    public function testValidateRejectsANonList(): void
    {
        $this->expectException(SegmentException::class);

        SegmentCriteria::validate('tag has AmTrek');
    }

    public function testPropertyDetection(): void
    {
        self::assertTrue(SegmentCriteria::isProperty('prop.lastBoughtProduct'));
        self::assertFalse(SegmentCriteria::isProperty('tag'));
    }

    /**
     * Narrowing a rule by one more condition — what a page automation does with
     * the locale of the page that triggered it. It has to hold whichever of the
     * three shapes a rule is stored in.
     */
    public function testNarrowingARule(): void
    {
        $locale = ['field' => 'locale', 'op' => '=', 'value' => 'fr'];
        $tag = ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'];
        $vip = ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek-VIP'];

        self::assertSame([$locale], CriteriaGroup::and([], $locale), 'an empty rule becomes the condition');
        self::assertSame([$tag, $locale], CriteriaGroup::and([$tag], $locale), 'a bare list is appended to');
        self::assertSame([$tag, $locale], CriteriaGroup::and(['all' => [$tag]], $locale), 'so is an explicit all');

        // The one that matters: appending to the `any` would widen the rule by
        // the very condition meant to narrow it, and spreading its conditions
        // into the list would turn "either tag" into "both".
        self::assertSame(
            [['any' => [$tag, $vip]], $locale],
            CriteriaGroup::and(['any' => [$tag, $vip]], $locale),
        );
    }

    /** Whatever shape it came from, the result is a rule the language accepts. */
    public function testANarrowedRuleStillNormalises(): void
    {
        $narrowed = CriteriaGroup::and(
            ['any' => [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]],
            ['field' => 'locale', 'op' => '=', 'value' => 'fr'],
        );

        self::assertSame('AND(OR(tag),locale)', $this->shape(SegmentCriteria::normalize($narrowed)));
    }
}
