<?php

namespace Pushword\Core\Tests\Query;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Query\ArrayCriteriaReader;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Group;

/**
 * The raw array form read into the tree.
 *
 * {@see \Pushword\Core\Tests\Repository\PageSearchCorpusTest} freezes what the
 * well-formed shapes compile to; what is here is the reading itself, and above
 * all what it refuses. This is the unvalidated escape hatch: it does not check
 * field names, so the few things it *does* check are the whole of its contract.
 */
final class ArrayCriteriaReaderTest extends TestCase
{
    /**
     * @param array<mixed> $where
     */
    private function read(array $where): Group|Condition|null
    {
        return new ArrayCriteriaReader()->read($where);
    }

    /** A compact rendering of the tree: `AND(h1,OR(slug,slug))`. */
    private function shape(Group|Condition|null $node): string
    {
        if (null === $node) {
            return 'nothing';
        }

        if ($node instanceof Condition) {
            return ($node->keyPrefix ?? '').$node->field;
        }

        return $node->conjunction->value.'('.implode(',', array_map($this->shape(...), $node->children)).')';
    }

    public function testAnEmptyRuleFiltersNothing(): void
    {
        self::assertNull($this->read([]));
    }

    /** A bare condition, not wrapped in a list — the static generator writes one. */
    public function testALooseConditionIsRead(): void
    {
        $condition = $this->read(['slug', 'LIKE', 'blog']);

        self::assertInstanceOf(Condition::class, $condition);
        self::assertSame('slug', $condition->field);
        self::assertSame('LIKE', $condition->operator);
        self::assertSame('blog', $condition->value);
    }

    public function testTheNamedFormIsRead(): void
    {
        $condition = $this->read([['key' => 'slug', 'operator' => '=', 'value' => 'blog', 'key_prefix' => 'parent.']]);

        self::assertInstanceOf(Condition::class, $condition);
        self::assertSame('parent.', $condition->keyPrefix);
    }

    /** Index 4, not 3: the positional form has always skipped one. */
    public function testThePositionalKeyPrefixIsIndexFour(): void
    {
        $condition = $this->read([['slug', '=', 'blog', null, 'parent.']]);

        self::assertInstanceOf(Condition::class, $condition);
        self::assertSame('parent.', $condition->keyPrefix);
    }

    /**
     * One marker anywhere makes the level an OR. That is not a mixed
     * expression — the form has no way to say one — and the surfaces that build
     * these arrays never emit a level holding both.
     */
    public function testAMarkerDecidesTheWholeLevel(): void
    {
        self::assertSame('OR(slug,slug)', $this->shape($this->read([['slug', '=', 'a'], 'OR', ['slug', '=', 'b']])));
        self::assertSame('AND(slug,slug)', $this->shape($this->read([['slug', '=', 'a'], ['slug', '=', 'b']])));
    }

    public function testAGroupIsToldFromAConditionByItsFirstElement(): void
    {
        self::assertSame(
            'AND(OR(h1,h1),slug)',
            $this->shape($this->read([[['h1', 'LIKE', '%a%'], 'OR', ['h1', 'LIKE', '%b%']], ['slug', 'LIKE', 'c']])),
        );
    }

    /** A group of one is not a group: it would add a level with no meaning. */
    public function testASingleChildCollapses(): void
    {
        self::assertSame('slug', $this->shape($this->read([['slug', 'LIKE', 'a']])));
    }

    /**
     * `()` in DQL is a syntax error, and the old compiler threw a message-less
     * exception on it. Saying what is wrong is the point of reading first.
     */
    public function testAnEmptyGroupIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/empty group/');

        $this->read([[], ['slug', 'LIKE', 'a']]);
    }

    public function testAnEmptyNestedGroupIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/empty group/');

        $this->read([['slug', 'LIKE', 'a'], 'OR', [[]]]);
    }

    public function testARowMustBeAnArrayOrAMarker(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be an array or a marker/');

        $this->read([['slug', 'LIKE', 'a'], 42]);
    }

    public function testAConditionNeedsAFieldName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/needs a field name/');

        $this->read([['key' => null, 'operator' => '=']]);
    }

    public function testAConditionNeedsAnOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/needs an operator/');

        $this->read([['slug']]);
    }

    public function testAFieldNameMustBeAString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/field name must be a string/');

        $this->read([[42, '=', 'a']]);
    }

    public function testAnOperatorMustBeAString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/operator must be a string/');

        $this->read([['slug', 42, 'a']]);
    }

    /** A prefix that is not a string is no prefix, not a fatal: the root alias applies. */
    public function testANonStringKeyPrefixFallsBackToTheRootAlias(): void
    {
        $condition = $this->read([['slug', '=', 'blog', null, 42]]);

        self::assertInstanceOf(Condition::class, $condition);
        self::assertNull($condition->keyPrefix);
    }
}
