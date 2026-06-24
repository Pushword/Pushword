<?php

namespace Pushword\Quiz\Twig;

use Override;
use Pushword\Quiz\Twig\TokenParser\QuizTokenParser;
use Twig\Extension\AbstractExtension;
use Twig\TokenParser\TokenParserInterface;

/**
 * Registers the `{% quiz %}…{% endquiz %}` tag. Kept separate from the
 * attribute-based {@see QuizExtension} because token parsers can only be
 * contributed through a classic Twig extension.
 */
final class QuizTagExtension extends AbstractExtension
{
    /**
     * @return TokenParserInterface[]
     */
    #[Override]
    public function getTokenParsers(): array
    {
        return [new QuizTokenParser()];
    }
}
