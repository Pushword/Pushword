<?php

namespace Pushword\Core\Query\Search;

use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Conjunction;
use Pushword\Core\Query\Group;

/**
 * Turns a search string into the {@see Group}/{@see Condition} tree.
 *
 *     expression := primary ( conjunction primary )*   — one conjunction throughout
 *     primary := '(' expression ')' | term
 *
 * There is no precedence: one level carries one conjunction. `a AND b OR c` is
 * refused, not silently read as `(a AND b) OR c` — the reader would have to know
 * a rule to know which, and the two answers differ. Parentheses say it instead,
 * and nothing is lost: `a AND (b OR c)` and `(a AND b) OR c` are both spellable.
 *
 * Nothing that worked before is refused now. The previous engine split on
 * `' OR '` first and never split the parts again, so `a AND b OR c` searched for
 * a *tag named* "a AND b" — a search that never worked now says so.
 *
 * Chains stay flat: `a OR b OR c` is one group of three, not two nested pairs.
 */
final readonly class SearchParser
{
    public function __construct(
        private PageSearchVocabulary $vocabulary,
        private SearchLexer $lexer = new SearchLexer(),
    ) {
    }

    /** @throws SearchException */
    public function parse(string $search): Group|Condition
    {
        $tokens = $this->lexer->tokenize($search);

        // An empty search is not an empty tree — there would be nothing to
        // compile. It has always meant "the tag named nothing", and still does.
        if ([] === $tokens) {
            return $this->vocabulary->term('');
        }

        $position = 0;
        $parsed = $this->parseExpression($tokens, $position, $search);

        if ($position < \count($tokens)) {
            throw new SearchException(\sprintf('Unexpected "%s" in "%s".', $tokens[$position][1], $search));
        }

        return $parsed;
    }

    /**
     * A chain of terms and groups, held together by one conjunction — the first
     * one met fixes it for the whole level.
     *
     * @param list<array{SearchTokenType, string}> $tokens
     *
     * @throws SearchException
     */
    private function parseExpression(array $tokens, int &$position, string $search): Group|Condition
    {
        $children = [$this->parsePrimary($tokens, $position, $search)];
        $conjunction = null;

        while (null !== ($next = $this->conjunctionAt($tokens, $position))) {
            if (null !== $conjunction && $next !== $conjunction) {
                throw new SearchException(\sprintf('"%s" mixes AND and OR without saying which comes first. Group one of them: "a AND (b OR c)" or "(a AND b) OR c".', $search));
            }

            $conjunction = $next;
            ++$position;
            $children[] = $this->parsePrimary($tokens, $position, $search);
        }

        return null === $conjunction ? $children[0] : new Group($conjunction, $children);
    }

    /**
     * @param list<array{SearchTokenType, string}> $tokens
     *
     * @throws SearchException
     */
    private function parsePrimary(array $tokens, int &$position, string $search): Group|Condition
    {
        $token = $tokens[$position] ?? throw new SearchException(\sprintf('"%s" ends where a term was expected.', $search));

        if (SearchTokenType::OpenGroup === $token[0]) {
            ++$position;

            if (SearchTokenType::CloseGroup === ($tokens[$position][0] ?? null)) {
                // The compiler cannot render an empty group, and would throw
                // without saying what went wrong. Say it here instead.
                throw new SearchException(\sprintf('Empty group in "%s".', $search));
            }

            $parsed = $this->parseExpression($tokens, $position, $search);

            if (SearchTokenType::CloseGroup !== ($tokens[$position][0] ?? null)) {
                throw new SearchException(\sprintf('Unclosed parenthesis in "%s".', $search));
            }

            ++$position;

            return $parsed;
        }

        if (SearchTokenType::Term !== $token[0]) {
            throw new SearchException(\sprintf('Expected a term, got "%s" in "%s".', $token[1], $search));
        }

        ++$position;

        return $this->vocabulary->term($token[1]);
    }

    /** @param list<array{SearchTokenType, string}> $tokens */
    private function conjunctionAt(array $tokens, int $position): ?Conjunction
    {
        $token = $tokens[$position] ?? null;

        return null !== $token && SearchTokenType::Conjunction === $token[0]
            ? Conjunction::from($token[1])
            : null;
    }
}
