<?php

namespace Pushword\Core\Query\Search;

use Pushword\Core\Query\Conjunction;

/**
 * Cuts a search into terms, conjunctions and group delimiters.
 *
 * A term is free text — `title:hello world` is one term, spaces included — so
 * the only thing that ends one is a delimiter, and the lexer's whole job is
 * knowing which characters are delimiters *here*:
 *
 * - `AND` and `OR` are conjunctions only as whole uppercase words with a space
 *   before them, exactly the boundary the previous `explode(' OR ')` used. A tag
 *   named `ORANGE`, or a lowercase `or`, is still ordinary text.
 * - `(` opens a group only where a term is expected — at the start, after a
 *   conjunction, or after another `(`. Anywhere else it is a character like any
 *   other, so the tag `foo (bar)` still reads as one term. That is what keeps
 *   this a strict superset of the searches written before parentheses existed.
 * - `)` closes a group only while one is open, for the same reason.
 */
final class SearchLexer
{
    /**
     * @return list<array{SearchTokenType, string}>
     *
     * @throws SearchException
     */
    public function tokenize(string $search): array
    {
        $tokens = [];
        $length = \strlen($search);
        $position = 0;
        $depth = 0;
        // Where a term may start — the position that decides whether `(` is
        // structural.
        $expectingTerm = true;

        while ($position < $length) {
            if (' ' === $search[$position]) {
                ++$position;

                continue;
            }

            if ($expectingTerm && '(' === $search[$position]) {
                $tokens[] = [SearchTokenType::OpenGroup, '('];
                ++$position;
                ++$depth;

                continue;
            }

            if (! $expectingTerm) {
                if (')' === $search[$position] && $depth > 0) {
                    $tokens[] = [SearchTokenType::CloseGroup, ')'];
                    ++$position;
                    --$depth;

                    continue;
                }

                $conjunction = $this->conjunctionAt($search, $position);

                if (null === $conjunction) {
                    throw new SearchException(\sprintf('Expected AND, OR or ")" at "%s".', substr($search, $position)));
                }

                $tokens[] = [SearchTokenType::Conjunction, $conjunction->value];
                $position += \strlen($conjunction->value);
                $expectingTerm = true;

                continue;
            }

            [$term, $position] = $this->readTerm($search, $position, $depth);
            $expectingTerm = false;

            // `()` — nothing between the delimiters. Emitting a token for it
            // would make the group look inhabited; leaving it out lets the
            // parser report the empty group it is.
            if ('' === $term) {
                continue;
            }

            $tokens[] = [SearchTokenType::Term, $term];
        }

        if ($depth > 0) {
            throw new SearchException(\sprintf('%d parenthesis left open in "%s".', $depth, $search));
        }

        if ($expectingTerm && [] !== $tokens) {
            throw new SearchException(\sprintf('"%s" ends on an operator.', $search));
        }

        return $tokens;
    }

    /**
     * Reads up to the next delimiter, keeping every space that is not the one
     * introducing a conjunction.
     *
     * @return array{string, int}
     */
    private function readTerm(string $search, int $position, int $depth): array
    {
        $start = $position;
        $length = \strlen($search);

        while ($position < $length) {
            if (')' === $search[$position] && $depth > 0) {
                break;
            }

            if (' ' === $search[$position]) {
                $next = $position;
                while ($next < $length && ' ' === $search[$next]) {
                    ++$next;
                }

                if ($next >= $length) {
                    break;
                }

                if (')' === $search[$next] && $depth > 0) {
                    break;
                }

                if (null !== $this->conjunctionAt($search, $next)) {
                    break;
                }
            }

            ++$position;
        }

        return [rtrim(substr($search, $start, $position - $start)), $position];
    }

    /** A conjunction only if the word ends there — `ORANGE` is a tag, not an `OR`. */
    private function conjunctionAt(string $search, int $position): ?Conjunction
    {
        foreach (Conjunction::cases() as $conjunction) {
            $word = $conjunction->value;

            if (substr($search, $position, \strlen($word)) !== $word) {
                continue;
            }

            $after = $search[$position + \strlen($word)] ?? ' ';

            if (' ' === $after || '(' === $after || ')' === $after) {
                return $conjunction;
            }
        }

        return null;
    }
}
