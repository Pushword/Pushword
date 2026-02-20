<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Pushword\Core\Component\EntityFilter\Filter\Date;

/**
 * Parse les shortcodes date dans le markdown.
 * Syntax: date(Y), date(M), date(S), date(W), etc.
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
        return InlineParserMatch::string('date(');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        $initialState = $cursor->saveState();

        $cursor->advanceBy(5);

        $format = $cursor->match('/^([^)]+)/');
        if (null === $format) {
            $cursor->restoreState($initialState);

            return false;
        }

        if (')' !== $cursor->getCharacter()) {
            $cursor->restoreState($initialState);

            return false;
        }

        $cursor->advanceBy(1);

        $shortcode = 'date('.$format.')';
        $dateString = $this->dateFilter->convertDateShortCode($shortcode, $this->locale);

        $inlineContext->getContainer()->appendChild(new Text($dateString));

        return true;
    }
}
