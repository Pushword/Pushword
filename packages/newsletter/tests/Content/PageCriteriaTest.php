<?php

namespace Pushword\Newsletter\Tests\Content;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Content\PageCriteria;
use Pushword\Newsletter\Segment\SegmentException;

final class PageCriteriaTest extends TestCase
{
    public function testNormalizeKeepsTheSupportedShapes(): void
    {
        $normalized = PageCriteria::normalize([
            ['field' => ' slug ', 'op' => 'startsWith', 'value' => 'blog/'],
            ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
            ['field' => 'parentPage', 'op' => '=', 'value' => 'blog'],
            ['field' => 'ancestor', 'op' => '!=', 'value' => 'blog'],
            ['field' => 'prop.noNewsletter', 'op' => 'isNotSet'],
        ]);

        self::assertSame(['any' => false, 'conditions' => [
            ['field' => 'slug', 'op' => 'startsWith', 'value' => 'blog/'],
            ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
            ['field' => 'parentPage', 'op' => '=', 'value' => 'blog'],
            ['field' => 'ancestor', 'op' => '!=', 'value' => 'blog'],
            ['field' => 'prop.noNewsletter', 'op' => 'isNotSet', 'value' => ''],
        ]], $normalized);
    }

    /** Both grammars carry their operator the same way. */
    public function testAGroupCarriesItsOperator(): void
    {
        $conditions = [['field' => 'tag', 'op' => 'has', 'value' => 'blog']];

        self::assertSame(['any' => true, 'conditions' => $conditions], PageCriteria::normalize(['any' => $conditions]));
        self::assertSame(['any' => false, 'conditions' => $conditions], PageCriteria::normalize(['all' => $conditions]));
    }

    public function testAnEmptyListIsValid(): void
    {
        self::assertSame(['any' => false, 'conditions' => []], PageCriteria::normalize([]));
    }

    /**
     * The segment grammar's fields must not silently leak into the page one.
     * `tag` is the exception, and a deliberate one: a page carries tags as a
     * contact does, so both sides spell it the same.
     */
    public function testAContactFieldIsRejected(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/unknown field "confirmedAt"/');

        PageCriteria::normalize([['field' => 'confirmedAt', 'op' => 'olderThan', 'value' => '7d']]);
    }

    public function testOperatorMustApplyToTheField(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/does not apply to "slug"/');

        PageCriteria::normalize([['field' => 'slug', 'op' => '=', 'value' => 'blog']]);
    }

    public function testAValueIsRequiredUnlessTheOperatorCarriesNone(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/needs a value/');

        PageCriteria::normalize([['field' => 'slug', 'op' => 'startsWith']]);
    }

    public function testAPropertyFieldNeedsAName(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/needs a property name/');

        PageCriteria::normalize([['field' => 'prop.', 'op' => '=', 'value' => 'x']]);
    }

    public function testAConditionMustBeAnObject(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/is not an object/');

        PageCriteria::normalize(['slug startsWith blog/']);
    }

    public function testTheJsonRoundTrip(): void
    {
        $criteria = [['field' => 'slug', 'op' => 'startsWith', 'value' => 'blog/']];

        self::assertSame($criteria, PageCriteria::fromJson(PageCriteria::toJson($criteria)));
        self::assertSame([], PageCriteria::fromJson(''));
        self::assertSame('', PageCriteria::toJson([]));
    }

    /**
     * The textarea's round trip must not lose the operator: an `any` coming back
     * as a bare list would silently become an `all`, and the rule would quietly
     * stop matching what it was written for.
     */
    public function testTheJsonRoundTripKeepsTheOperator(): void
    {
        $rule = ['any' => [['field' => 'tag', 'op' => 'has', 'value' => 'blog']]];

        self::assertSame($rule, PageCriteria::fromJson(PageCriteria::toJson($rule)));
    }

    public function testAGroupMustHoldAList(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/must hold a list of conditions/');

        PageCriteria::normalize(['any' => 'blog']);
    }

    public function testMalformedJsonIsRejected(): void
    {
        $this->expectException(SegmentException::class);

        PageCriteria::fromJson('{ not json');
    }
}
