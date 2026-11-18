<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Pushword\Core\Component\EntityFilter\Filter\Date;

/**
 * Parse date and elapsed-year shortcodes in Markdown.
 * Syntax: date(Y), date(M), yearsSince(2014), yearsSince(2014-06-15), etc.
 */
final readonly class DateShortcodeParser implements InlineParserInterface
{
    public function __construct(
        private Date $dateFilter,
        private string $locale,
    ) {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('date\(|\byearsSince\(');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        $initialState = $cursor->saveState();

        $cursor->advanceBy($inlineContext->getFullMatchLength());

        $argument = $cursor->match('/^([^)]+)/');
        if (null === $argument) {
            $cursor->restoreState($initialState);

            return false;
        }

        if (')' !== $cursor->getCharacter()) {
            $cursor->restoreState($initialState);

            return false;
        }

        $cursor->advanceBy(1);

        $shortcode = $inlineContext->getFullMatch().$argument.')';
        $replacement = $this->dateFilter->convertDateShortCode($shortcode, $this->locale);

        $inlineContext->getContainer()->appendChild(new Text($replacement));

        return true;
    }
}
