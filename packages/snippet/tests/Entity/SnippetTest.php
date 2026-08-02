<?php

namespace Pushword\Snippet\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Snippet\Entity\Snippet;

/**
 * The three columns carry their normalization in property hooks: the slug is the
 * key `snippet('...')` looks up, so it has to be canonical whoever writes it — the
 * admin form, the API, or a flat import.
 */
final class SnippetTest extends TestCase
{
    public function testTheSlugIsNormalizedOnWrite(): void
    {
        $snippet = new Snippet();
        $snippet->slug = '  CTA-Footer  ';

        self::assertSame('cta-footer', $snippet->slug);
    }

    public function testTheNameFallsBackToTheSlug(): void
    {
        $snippet = new Snippet();
        $snippet->slug = 'cta';

        self::assertSame('cta', $snippet->name);
        self::assertSame('cta', (string) $snippet);

        $snippet->name = 'Call to action';
        self::assertSame('Call to action', $snippet->name);
        self::assertSame('Call to action', (string) $snippet);
    }

    public function testANullNameFallsBackToTheSlugRatherThanFailing(): void
    {
        $snippet = new Snippet();
        $snippet->slug = 'cta';
        $snippet->name = 'Call to action';
        self::assertSame('Call to action', $snippet->name);

        // The admin form posts a cleared field as null; the fallback has to come back.
        $snippet->name = null;

        self::assertSame('cta', $snippet->name);
    }

    public function testANullContentIsStoredAsAnEmptyString(): void
    {
        $snippet = new Snippet();
        $snippet->content = null;

        self::assertSame('', $snippet->content);
    }
}
