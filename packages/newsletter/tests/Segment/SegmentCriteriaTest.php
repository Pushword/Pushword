<?php

namespace Pushword\Newsletter\Tests\Segment;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;

final class SegmentCriteriaTest extends TestCase
{
    public function testNormalizeKeepsTheThreeSupportedShapes(): void
    {
        $normalized = SegmentCriteria::normalize([
            ['field' => ' tag ', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'createdAt', 'op' => 'olderThan', 'value' => '7d'],
            ['field' => 'prop.lastBoughtProduct', 'op' => '=', 'value' => 'tmb'],
        ]);

        self::assertSame(['any' => false, 'conditions' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
            ['field' => 'createdAt', 'op' => 'olderThan', 'value' => '7d'],
            ['field' => 'prop.lastBoughtProduct', 'op' => '=', 'value' => 'tmb'],
        ]], $normalized);
    }

    /** A bare list is ANDed; `any` is the one thing a rule has to say out loud. */
    public function testAGroupCarriesItsOperator(): void
    {
        $conditions = [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']];

        self::assertSame(['any' => true, 'conditions' => $conditions], SegmentCriteria::normalize(['any' => $conditions]));
        self::assertSame(['any' => false, 'conditions' => $conditions], SegmentCriteria::normalize(['all' => $conditions]));
        self::assertSame(['any' => false, 'conditions' => $conditions], SegmentCriteria::normalize($conditions));
    }

    /**
     * The textarea's round trip must not lose the operator: an `any` coming back
     * as a bare list would silently become an `all`, and a segment written to
     * reach two groups would reach only their intersection.
     */
    public function testTheJsonRoundTripKeepsTheOperator(): void
    {
        $rule = ['any' => [['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]];

        self::assertSame($rule, SegmentCriteria::fromJson(SegmentCriteria::toJson($rule)));
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
        $normalized = SegmentCriteria::normalize([
            ['field' => 'prop.x', 'op' => 'isSet', 'value' => 'ignored'],
        ]);

        self::assertSame('', $normalized['conditions'][0]['value']);
    }

    public function testAnEmptyListIsValid(): void
    {
        self::assertSame(['any' => false, 'conditions' => []], SegmentCriteria::normalize([]));
    }

    public function testUnknownFieldIsRejected(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/unknown field "email"/');

        SegmentCriteria::normalize([['field' => 'email', 'op' => '=', 'value' => 'a@b.c']]);
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

    public function testValidateRejectsANonList(): void
    {
        $this->expectException(SegmentException::class);

        SegmentCriteria::validate('tag has AmTrek');
    }

    public function testPropertyPath(): void
    {
        self::assertTrue(SegmentCriteria::isProperty('prop.lastBoughtProduct'));
        self::assertFalse(SegmentCriteria::isProperty('tag'));
        self::assertSame('$.lastBoughtProduct', SegmentCriteria::propertyPath('prop.lastBoughtProduct'));
    }
}
