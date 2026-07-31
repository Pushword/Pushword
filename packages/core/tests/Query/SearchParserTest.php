<?php

namespace Pushword\Core\Tests\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Group;
use Pushword\Core\Query\Search\PageSearchVocabulary;
use Pushword\Core\Query\Search\SearchException;
use Pushword\Core\Query\Search\SearchParser;

/**
 * The shape a search parses into, independently of what it compiles to.
 *
 * {@see \Pushword\Core\Tests\Repository\PageSearchCorpusTest} freezes the DQL;
 * this covers the grammar itself — precedence, grouping, and the boundaries that
 * keep parentheses and conjunctions from swallowing text that used to be part of
 * a tag name.
 */
final class SearchParserTest extends TestCase
{
    private function parser(): SearchParser
    {
        return new SearchParser(new PageSearchVocabulary());
    }

    /**
     * A compact rendering of the tree: `AND(OR(h1,title),parentPage)`. Structure
     * and field names only — the shape is what these assertions are about.
     */
    private function shape(Group|Condition $node): string
    {
        if ($node instanceof Condition) {
            return ($node->keyPrefix ?? '').$node->field;
        }

        return $node->conjunction->value.'('.implode(',', array_map($this->shape(...), $node->children)).')';
    }

    /** @return iterable<string, array{string, string}> */
    public static function grammar(): iterable
    {
        yield 'a lone term is not wrapped in a group' => ['blog', 'tag'];

        yield 'AND binds tighter than OR' => [
            'a AND b OR c', 'OR(AND(tag,tag),tag)',
        ];

        yield 'and on the other side too' => [
            'a OR b AND c', 'OR(tag,AND(tag,tag))',
        ];

        yield 'parentheses override precedence' => [
            'a AND (b OR c)', 'AND(tag,OR(tag,tag))',
        ];

        yield 'a chain stays flat' => [
            'a OR b OR c OR d', 'OR(tag,tag,tag,tag)',
        ];

        yield 'an AND chain stays flat too' => [
            'a AND b AND c', 'AND(tag,tag,tag)',
        ];

        yield 'groups nest' => [
            '((a OR b) AND c) OR d', 'OR(AND(OR(tag,tag),tag),tag)',
        ];

        yield 'a redundant group is transparent' => [
            '(a)', 'tag',
        ];

        yield 'a term expanding to a group nests like any other' => [
            'title:x AND b', 'AND(OR(h1,title),tag)',
        ];

        // `parent` names a field, not a column: that it is read through a join is
        // the registry's business, not the parser's.
        yield 'prefixes name registry fields' => [
            'parent:blog OR template:x', 'OR(parent,template)',
        ];

        // The space belongs to the term: only a space introducing a conjunction ends one.
        yield 'a prefixed term keeps its spaces' => [
            'title:hello world OR b', 'OR(OR(h1,title),tag)',
        ];
    }

    /**
     * Every one of these parsed as a single tag before parentheses and
     * precedence existed, and still does. That is the property that makes the
     * new grammar a superset rather than a replacement.
     *
     * @return iterable<string, array{string}>
     */
    public static function preservedAsPlainText(): iterable
    {
        yield 'a parenthesis inside a term' => ['foo (bar)'];

        yield 'an unbalanced closing parenthesis outside any group' => ['foo)'];

        yield 'a word starting with OR' => ['ORANGE'];

        yield 'a lowercase conjunction' => ['a or b'];

        yield 'a tag holding spaces' => ['hello world'];

        yield 'a leading AND has no space before it' => ['AND foo'];
    }

    #[DataProvider('grammar')]
    public function testParsesTo(string $search, string $shape): void
    {
        self::assertSame($shape, $this->shape($this->parser()->parse($search)));
    }

    #[DataProvider('preservedAsPlainText')]
    public function testStaysASingleTerm(string $search): void
    {
        $parsed = $this->parser()->parse($search);

        self::assertInstanceOf(Condition::class, $parsed);
        self::assertSame('tag', $parsed->field);
        self::assertSame('has', $parsed->operator);
        self::assertSame($search, $parsed->value);
    }

    /** @return iterable<string, array{string, string}> */
    public static function structuralMistakes(): iterable
    {
        yield 'an unclosed group' => ['(a OR b', 'left open'];

        yield 'an empty group' => ['() AND a', 'Empty group'];

        yield 'a dangling conjunction' => ['a OR', 'ends on an operator'];

        yield 'two terms with nothing between them' => ['(a) b', 'Expected AND, OR'];
    }

    #[DataProvider('structuralMistakes')]
    public function testIsRefused(string $search, string $message): void
    {
        $this->expectException(SearchException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($message, '/').'/');

        $this->parser()->parse($search);
    }

    /**
     * An unknown prefix is never a structural mistake. `type:product` is a
     * namespaced tag carried by 1167 pages on one production site; the parser
     * cannot tell it from a mistyped `tag:`, so neither is an error.
     */
    public function testAnUnknownPrefixIsATagAndNotAnError(): void
    {
        $parsed = $this->parser()->parse('type:product');

        self::assertInstanceOf(Condition::class, $parsed);
        self::assertSame('tag', $parsed->field);
        self::assertSame('type:product', $parsed->value);
    }

    /** An empty search has always meant the tag named nothing; it still does. */
    public function testAnEmptySearchIsNotAnEmptyTree(): void
    {
        $parsed = $this->parser()->parse('');

        self::assertInstanceOf(Condition::class, $parsed);
        self::assertSame('tag', $parsed->field);
        self::assertSame('', $parsed->value);
    }
}
