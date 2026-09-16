<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Tempest\Markdown\Parser;
use Tempest\Markdown\ProvidesFirstChar;
use Tempest\Markdown\Rule;
use Tempest\Markdown\Rules\HeadingRule;
use Tempest\Markdown\Token;
use Tempest\Markdown\Tokens\HeadingToken;

/**
 * Tempest slugs an id onto every heading. Pushword adds heading ids later in
 * the render chain, from the whole page rather than from one block, so the
 * parser must leave them out.
 *
 * Replaceable by `new HeadingRule(generateIds: false)` once the dependency
 * moves past 1.3.0 — see tempestphp/markdown#46.
 */
final class HeadingWithoutIdRule implements Rule, ProvidesFirstChar
{
    public string $firstChar = '#';

    public function __construct(
        private readonly HeadingRule $headings = new HeadingRule(),
    ) {
    }

    public function shouldParse(Parser $parser): bool
    {
        return $this->headings->shouldParse($parser);
    }

    public function parse(Parser $parser): Token
    {
        $token = $this->headings->parse($parser);

        if (! $token instanceof HeadingToken) {
            return $token;
        }

        return new HeadingToken($token->content, $token->level);
    }
}
