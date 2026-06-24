<?php

namespace Pushword\Quiz\Twig\TokenParser;

use Pushword\Quiz\Twig\Node\QuizNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses `{% quiz %}…{% endquiz %}`. The body is sub-parsed as Twig, so plain
 * JSON passes through verbatim (apostrophes and double quotes need no escaping)
 * while any `{{ … }}` it contains is still interpolated — bear in mind the
 * rendered body is then JSON-decoded, so only quote-free output is safe inside.
 */
final class QuizTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): QuizNode
    {
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);

        $body = $this->parser->subparse($this->decideEnd(...), true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new QuizNode($body, $token->getLine());
    }

    public function decideEnd(Token $token): bool
    {
        return $token->test('endquiz');
    }

    public function getTag(): string
    {
        return 'quiz';
    }
}
