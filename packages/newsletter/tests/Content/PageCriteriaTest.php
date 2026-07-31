<?php

namespace Pushword\Newsletter\Tests\Content;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Group;
use Pushword\Newsletter\Content\PageCriteria;
use Pushword\Newsletter\Segment\SegmentException;

final class PageCriteriaTest extends TestCase
{
    /** A compact rendering of the tree: `AND(tag,OR(slug,template))`. */
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

    public function testNormalizeReadsTheSupportedShapes(): void
    {
        $rule = PageCriteria::normalize([
            ['field' => ' slug ', 'op' => 'startsWith', 'value' => 'blog/'],
            ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
            ['field' => 'parent', 'op' => '=', 'value' => 'blog'],
            ['field' => 'ancestor', 'op' => '!=', 'value' => 'blog'],
            ['field' => 'prop.noNewsletter', 'op' => 'isNotSet'],
        ]);

        self::assertSame('AND(slug,template,parent,ancestor,prop.noNewsletter)', $this->shape($rule));
        self::assertInstanceOf(Group::class, $rule);
        $first = $rule->children[0];
        self::assertInstanceOf(Condition::class, $first);
        self::assertSame('blog/', $first->value);
    }

    public function testAConditionKeepsItsFieldAndOperator(): void
    {
        $rule = PageCriteria::normalize([['field' => 'tag', 'op' => 'has', 'value' => 'blog']]);

        self::assertInstanceOf(Group::class, $rule);
        $condition = $rule->children[0];
        self::assertInstanceOf(Condition::class, $condition);
        self::assertSame('tag', $condition->field);
        self::assertSame('has', $condition->operator);
        self::assertSame('blog', $condition->value);
    }

    /** Both grammars carry their operator the same way. */
    public function testAGroupCarriesItsOperator(): void
    {
        $conditions = [
            ['field' => 'tag', 'op' => 'has', 'value' => 'blog'],
            ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
        ];

        self::assertSame('OR(tag,tag)', $this->shape(PageCriteria::normalize(['any' => $conditions])));
        self::assertSame('AND(tag,tag)', $this->shape(PageCriteria::normalize(['all' => $conditions])));
        self::assertSame('AND(tag,tag)', $this->shape(PageCriteria::normalize($conditions)));
    }

    /**
     * The rule that had no shape before: a section, restricted to either of two
     * tags. Two triggers do not replace it — an article carrying both tags would
     * match both and be mailed twice.
     */
    public function testAChildMayBeAGroupOfItsOwn(): void
    {
        $rule = PageCriteria::normalize([
            ['field' => 'ancestor', 'op' => '=', 'value' => 'blog'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'pinned'],
            ]],
        ]);

        self::assertSame('AND(ancestor,OR(tag,tag))', $this->shape($rule));
    }

    public function testGroupsNest(): void
    {
        $rule = PageCriteria::normalize(['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'a'],
            ['all' => [
                ['field' => 'ancestor', 'op' => '=', 'value' => 'blog'],
                ['any' => [
                    ['field' => 'template', 'op' => '=', 'value' => 'x'],
                    ['field' => 'template', 'op' => '=', 'value' => 'y'],
                ]],
            ]],
        ]]);

        self::assertSame('OR(tag,AND(ancestor,OR(template,template)))', $this->shape($rule));
    }

    public function testAnEmptyListFiltersNothing(): void
    {
        self::assertNull(PageCriteria::normalize([]));
    }

    public function testAnEmptyNestedGroupIsRejected(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/#1 is an empty group/');

        PageCriteria::normalize([
            ['field' => 'tag', 'op' => 'has', 'value' => 'a'],
            ['any' => []],
        ]);
    }

    /** A nested mistake says where it is. */
    public function testANestedConditionIsReportedByItsPath(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/Condition #1\.1/');

        PageCriteria::normalize([
            ['field' => 'tag', 'op' => 'has', 'value' => 'a'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'b'],
                ['field' => 'nope', 'op' => '=', 'value' => 'c'],
            ]],
        ]);
    }

    /**
     * The two vocabularies overlap — `tag` and `prop.*` deliberately read the
     * same on both sides — so a field of the other one is named rather than
     * merely refused.
     */
    public function testAContactFieldSaysSo(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/"confirmedAt" filters a contact, not a page/');

        PageCriteria::normalize([['field' => 'confirmedAt', 'op' => 'olderThan', 'value' => '7d']]);
    }

    /** A trigger has no page being rendered, so the contextual searches cannot mean anything. */
    public function testAContextualFieldSaysWhyItCannotBeUsed(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/"children" cannot be used here.*no current page/');

        PageCriteria::normalize([['field' => 'children', 'op' => '=', 'value' => 'x']]);
    }

    /** The trigger already bounds it, twice. */
    public function testPublishedAtIsRefusedWithItsReason(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/"publishedAt" cannot be used here.*triggerFrom/');

        PageCriteria::normalize([['field' => 'publishedAt', 'op' => '>', 'value' => '2026-01-01']]);
    }

    public function testAnUnknownFieldListsTheKnownOnes(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/unknown field "nope"/');

        PageCriteria::normalize([['field' => 'nope', 'op' => '=', 'value' => 'x']]);
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

    public function testValuelessOperatorsDropTheirValue(): void
    {
        $rule = PageCriteria::normalize([['field' => 'prop.x', 'op' => 'isSet', 'value' => 'ignored']]);

        self::assertInstanceOf(Group::class, $rule);
        $condition = $rule->children[0];
        self::assertInstanceOf(Condition::class, $condition);
        self::assertSame('', $condition->value);
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
     * The textarea's round trip must not lose an operator: an `any` coming back
     * as a bare list would silently become an `all`, and the rule would quietly
     * stop matching what it was written for. A nested group therefore always
     * names its operator, `all` included.
     */
    public function testTheJsonRoundTripKeepsEveryOperator(): void
    {
        $rule = ['any' => [['field' => 'tag', 'op' => 'has', 'value' => 'blog']]];
        self::assertSame($rule, PageCriteria::fromJson(PageCriteria::toJson($rule)));

        $nested = [
            ['field' => 'ancestor', 'op' => '=', 'value' => 'blog'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'pinned'],
            ]],
        ];
        self::assertSame($nested, PageCriteria::fromJson(PageCriteria::toJson($nested)));
    }

    public function testAGroupMustHoldAList(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/must hold a list of conditions/');

        PageCriteria::normalize(['any' => 'blog']);
    }

    /**
     * An input opening on a bracket was reaching for JSON. Reading it as a
     * search instead would store the typo as a tag and report nothing.
     */
    public function testMalformedJsonIsRejectedAsJson(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/must be a JSON list/');

        PageCriteria::fromJson('{ not json');
    }

    /**
     * The other way to write a rule: the `pages_list` search an editor already
     * knows. The string is an input; what gets stored is the list it means.
     */
    public function testARuleMayBeWrittenAsASearch(): void
    {
        self::assertSame(
            [
                ['field' => 'ancestor', 'op' => '=', 'value' => 'blog'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
            ],
            PageCriteria::fromJson('ancestor:blog AND tag:featured'),
        );
    }

    /** Grouping comes with it, so does the nesting it produces. */
    public function testASearchMayGroup(): void
    {
        self::assertSame(
            [
                ['field' => 'ancestor', 'op' => '=', 'value' => 'blog'],
                ['any' => [
                    ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
                    ['field' => 'tag', 'op' => 'has', 'value' => 'pinned'],
                ]],
            ],
            PageCriteria::fromJson('ancestor:blog AND (tag:featured OR tag:pinned)'),
        );
    }

    /**
     * The search grammar is wider than a trigger's vocabulary, and the trigger's
     * is what decides: a term it has no business filtering on is refused by name
     * rather than quietly compiled.
     */
    public function testASearchIsStillValidatedAgainstTheTriggerVocabulary(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/unknown field "h1"/');

        PageCriteria::fromJson('title:whatever');
    }

    /** A trigger runs outside any page, so the contextual searches say so. */
    public function testASearchCannotUseAContextualTerm(): void
    {
        $this->expectException(SegmentException::class);
        $this->expectExceptionMessageMatches('/no current page/');

        PageCriteria::fromJson('children');
    }
}
