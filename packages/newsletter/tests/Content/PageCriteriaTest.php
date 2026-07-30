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

        self::assertSame([
            ['field' => 'slug', 'op' => 'startsWith', 'value' => 'blog/'],
            ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
            ['field' => 'parentPage', 'op' => '=', 'value' => 'blog'],
            ['field' => 'ancestor', 'op' => '!=', 'value' => 'blog'],
            ['field' => 'prop.noNewsletter', 'op' => 'isNotSet', 'value' => ''],
        ], $normalized);
    }

    public function testAnEmptyListIsValid(): void
    {
        self::assertSame([], PageCriteria::normalize([]));
    }

    /** The segment grammar's fields must not silently leak into the page one. */
    public function testAContactFieldIsRejected(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/unknown field "tag"/');

        PageCriteria::normalize([['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']]);
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

    public function testMalformedJsonIsRejected(): void
    {
        $this->expectException(SegmentException::class);

        PageCriteria::fromJson('{ not json');
    }
}
