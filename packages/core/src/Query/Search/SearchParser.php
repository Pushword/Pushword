<?php

namespace Pushword\Core\Query\Search;

use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Conjunction;
use Pushword\Core\Query\Group;

/**
 * Turns a search string into the {@see Group}/{@see Condition} tree.
 *
 *     expression := conjunction ( 'OR' conjunction )*
 *     conjunction := primary ( 'AND' primary )*
 *     primary := '(' expression ')' | term
 *
 * `AND` binds tighter than `OR`, as it does in SQL and everywhere else. No
 * existing search can change meaning because of it: the previous engine split on
 * `' OR '` first and never split the parts again, so a mixed expression already
 * did not work — `a AND b OR c` searched for a *tag named* "a AND b". Precedence
 * only decides cases that used to be broken.
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
     * @param list<array{SearchTokenType, string}> $tokens
     *
     * @throws SearchException
     */
    private function parseExpression(array $tokens, int &$position, string $search): Group|Condition
    {
        return $this->parseChain($tokens, $position, $search, Conjunction::Any);
    }

    /**
     * One precedence level: parse the tighter level, then keep going while the
     * next token is this level's conjunction.
     *
     * @param list<array{SearchTokenType, string}> $tokens
     *
     * @throws SearchException
     */
    private function parseChain(array $tokens, int &$position, string $search, Conjunction $conjunction): Group|Condition
    {
        $children = [$this->parseTighterThan($conjunction, $tokens, $position, $search)];

        while ($this->nextIs($tokens, $position, $conjunction)) {
            ++$position;
            $children[] = $this->parseTighterThan($conjunction, $tokens, $position, $search);
        }

        return 1 === \count($children) ? $children[0] : new Group($conjunction, $children);
    }

    /**
     * The level below this one — which is what precedence *is*: an `OR` chain is
     * made of `AND` chains, and an `AND` chain is made of terms and groups.
     *
     * @param list<array{SearchTokenType, string}> $tokens
     *
     * @throws SearchException
     */
    private function parseTighterThan(Conjunction $conjunction, array $tokens, int &$position, string $search): Group|Condition
    {
        return Conjunction::Any === $conjunction
            ? $this->parseChain($tokens, $position, $search, Conjunction::All)
            : $this->parsePrimary($tokens, $position, $search);
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
    private function nextIs(array $tokens, int $position, Conjunction $conjunction): bool
    {
        $token = $tokens[$position] ?? null;

        return null !== $token
            && SearchTokenType::Conjunction === $token[0]
            && $token[1] === $conjunction->value;
    }
}
